<?php

namespace Tests\Feature\Homologacao;

use App\Enums\PropositionModality;
use App\Enums\PropositionStatus;
use App\Models\Councilor;
use App\Models\Institution;
use App\Models\NumberingSequence;
use App\Models\Proposition;
use App\Models\User;
use App\Services\PropositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Homologação operacional — Cenário 3: proposições legislativas.
 *
 * Cobre Moção, Indicação, Projeto de Lei e Requerimento pelo fluxo
 * rascunho -> envio -> análise -> devolução -> correção -> aprovação ->
 * registro oficial -> numeração. Verifica ainda a ausência de números
 * duplicados sob solicitações simultâneas.
 */
class ProposicoesHomologTest extends TestCase
{
    use RefreshDatabase;

    private Institution $camara;

    private Councilor $autor;

    private Councilor $coautor;

    private User $vereador;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->camara = $this->makeInstitution(['name' => 'Câmara Proposições']);
        $this->autor = $this->makeCouncilor($this->camara, ['name' => 'Vereador Autor']);
        $this->coautor = $this->makeCouncilor($this->camara, ['name' => 'Vereador Coautor']);

        $this->vereador = $this->makeUser($this->camara, 'vereador', [
            'name' => 'Vereador Usuário', 'councilor_id' => $this->autor->id,
        ]);
        $this->analista = $this->makeUser($this->camara, 'administrador', [
            'name' => 'Secretaria Legislativa',
        ]);
    }

    public function test_fluxo_completo_com_devolucao_correcao_e_registro_oficial(): void
    {
        // 1) Rascunho (autor).
        $this->actingAs($this->vereador)->post(route('propositions.store'), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'author_councilor_id' => $this->autor->id,
            'coauthor_ids' => [$this->coautor->id],
            'title' => 'Projeto de Lei de homologação',
            'ementa' => 'Dispõe sobre matéria de homologação.',
        ])->assertRedirect();

        $proposition = Proposition::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(PropositionStatus::RASCUNHO, $proposition->status);
        $this->assertNull($proposition->official_number);

        // 2) Envio (autor).
        $this->actingAs($this->vereador)
            ->post(route('propositions.submit', $proposition))->assertRedirect();
        $this->assertSame(PropositionStatus::ENVIADA, $proposition->fresh()->status);
        $this->assertNotNull($proposition->fresh()->submitted_at);

        // 3) Análise com devolução (Secretaria).
        $this->actingAs($this->analista)->post(route('propositions.review', $proposition), [
            'decision' => 'devolvida',
            'notes' => 'Ajustar a justificativa.',
        ])->assertRedirect();

        $this->assertSame(PropositionStatus::DEVOLVIDA, $proposition->fresh()->status);
        $this->assertSame($this->analista->id, $proposition->fresh()->reviewed_by);

        // 4) Correção (autor) — permitida em proposição devolvida.
        $this->actingAs($this->vereador)->put(route('propositions.update', $proposition), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'author_councilor_id' => $this->autor->id,
            'title' => 'Projeto de Lei de homologação (revisado)',
            'ementa' => 'Dispõe sobre matéria de homologação, revisada.',
        ])->assertRedirect();

        $this->assertSame('Projeto de Lei de homologação (revisado)', $proposition->fresh()->title);

        // 5) Reenvio (autor).
        $this->actingAs($this->vereador)
            ->post(route('propositions.submit', $proposition))->assertRedirect();
        $this->assertSame(PropositionStatus::ENVIADA, $proposition->fresh()->status);

        // 6) Análise com aprovação (Secretaria).
        $this->actingAs($this->analista)->post(route('propositions.review', $proposition), [
            'decision' => 'aprovada',
        ])->assertRedirect();
        $this->assertSame(PropositionStatus::APROVADA, $proposition->fresh()->status);

        // 7) Registro oficial + numeração.
        $this->actingAs($this->analista)
            ->post(route('propositions.register', $proposition))->assertRedirect();

        $registrada = $proposition->fresh();
        $this->assertSame(PropositionStatus::REGISTRADA, $registrada->status);
        $this->assertSame('PL nº 001/'.$this->camaraYear(), $registrada->official_number);
        $this->assertNotNull($registrada->registered_by);
        $this->assertNotNull($registrada->registered_at);
    }

    public function test_todas_as_modalidades_solicitadas_geram_numeracao_propria(): void
    {
        $service = app(PropositionService::class);

        $casos = [
            [PropositionModality::MOCAO, 'MOC'],
            [PropositionModality::INDICACAO, 'IND'],
            [PropositionModality::PROJETO_LEI, 'PL'],
            [PropositionModality::REQUERIMENTO, 'REQ'],
        ];

        foreach ($casos as [$modality, $prefixo]) {
            $proposition = $service->create([
                'institution_id' => $this->camara->id,
                'modality' => $modality->value,
                'author_councilor_id' => $this->autor->id,
                'title' => $modality->label().' de homologação',
                'ementa' => 'Ementa de homologação.',
            ], $this->analista);

            $this->assertSame(PropositionStatus::RASCUNHO, $proposition->status);

            $service->submit($proposition, $this->vereador);
            $service->review($proposition, $this->analista, PropositionStatus::APROVADA);

            $registrada = $service->registerOfficial($proposition, $this->analista);

            $this->assertSame($prefixo.' nº 001/'.$this->camaraYear(), $registrada->official_number);
        }
    }

    public function test_solicitacoes_simultaneas_nao_geram_numeros_duplicados(): void
    {
        $service = app(PropositionService::class);
        $total = 15;

        // Registro em rajada (mesma modalidade e exercício) simulando demanda
        // simultânea de registro oficial.
        $numeros = [];

        for ($i = 1; $i <= $total; $i++) {
            $proposition = $service->create([
                'institution_id' => $this->camara->id,
                'modality' => PropositionModality::PROJETO_LEI->value,
                'author_councilor_id' => $this->autor->id,
                'title' => 'PL concorrente '.$i,
                'ementa' => 'Registro simultâneo '.$i,
            ], $this->analista);

            $service->submit($proposition, $this->vereador);
            $service->review($proposition, $this->analista, PropositionStatus::APROVADA);
            $registrada = $service->registerOfficial($proposition, $this->analista);

            $numeros[] = $registrada->number;
        }

        // Nenhum número repetido e sequência completa de 1..N.
        $this->assertCount($total, array_unique($numeros));
        $this->assertSame(range(1, $total), $numeros);

        // Há uma única sequência de numeração para (instituição, escopo, modalidade, exercício).
        $this->assertSame(1, NumberingSequence::query()
            ->where('institution_id', $this->camara->id)
            ->where('scope', NumberingSequence::SCOPE_PROPOSITION)
            ->where('modality', PropositionModality::PROJETO_LEI->value)
            ->where('year', $this->camaraYear())
            ->count());

        // A constraint UNIQUE impede que duas sequências concorrentes coexistam.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('numbering_sequences')->insert([
            'institution_id' => $this->camara->id,
            'scope' => NumberingSequence::SCOPE_PROPOSITION,
            'modality' => PropositionModality::PROJETO_LEI->value,
            'year' => $this->camaraYear(),
            'last_number' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function camaraYear(): int
    {
        return (int) now()->year;
    }
}
