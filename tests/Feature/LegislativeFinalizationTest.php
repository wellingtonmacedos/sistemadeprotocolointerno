<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\LegislativeSession;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Models\SessionAgendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 5 — Indicadores do dashboard e filtros do relatório de proposições.
 */
class LegislativeFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    protected function makeProposition($institution, array $attributes = []): Proposition
    {
        return Proposition::withoutGlobalScopes()->create(array_merge([
            'institution_id' => $institution->id,
            'modality' => 'projeto_lei',
            'year' => (int) now()->year,
            'title' => 'Proposição de teste',
            'ementa' => 'Ementa de teste',
            'status' => 'registrada',
            'author_councilor_id' => $this->makeCouncilor($institution)->id,
        ], $attributes));
    }

    public function test_dashboard_shows_legislative_indicators(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $this->makeProposition($institution);

        $this->actingAs($admin)
            ->get(route('painel'))
            ->assertOk()
            ->assertSee('Indicadores legislativos')
            ->assertSee('Protocoladas')
            ->assertSee('Aguardando parecer')
            ->assertSee('Aguardando pauta')
            ->assertSee('Promulgadas');
    }

    public function test_dashboard_indicators_are_isolated_between_institutions(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $this->makeProposition($b, ['title' => 'Matéria Secreta da Câmara B']);

        $this->actingAs($adminA)
            ->get(route('painel'))
            ->assertOk()
            ->assertDontSee('Matéria Secreta da Câmara B');
    }

    public function test_proposition_report_filters_by_author(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $authorA = $this->makeCouncilor($institution);
        $authorB = $this->makeCouncilor($institution);

        $this->makeProposition($institution, ['title' => 'Matéria Alfa', 'author_councilor_id' => $authorA->id]);
        $this->makeProposition($institution, ['title' => 'Matéria Beta', 'author_councilor_id' => $authorB->id]);

        $this->actingAs($admin)
            ->get(route('reports.propositions', ['author_councilor_id' => $authorA->id]))
            ->assertOk()
            ->assertSee('Matéria Alfa')
            ->assertDontSee('Matéria Beta');
    }

    public function test_proposition_report_filters_by_session(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $session = LegislativeSession::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'number' => 1,
            'type' => 'ordinaria',
            'date' => '2026-03-10',
            'status' => 'agendada',
        ]);

        $scheduled = $this->makeProposition($institution, ['title' => 'Matéria Pautada']);
        $this->makeProposition($institution, ['title' => 'Matéria Fora da Pauta']);

        SessionAgendaItem::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'session_id' => $session->id,
            'proposition_id' => $scheduled->id,
            'position' => 1,
            'appreciation_type' => 'discussao_e_votacao',
            'status' => 'pautada',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.propositions', ['session_id' => $session->id]))
            ->assertOk()
            ->assertSee('Matéria Pautada')
            ->assertDontSee('Matéria Fora da Pauta');
    }

    public function test_proposition_report_filters_by_legislative_stage(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->makeProposition($institution, ['title' => 'Matéria Aprovada', 'status' => 'aprovada']);
        $this->makeProposition($institution, ['title' => 'Matéria Rejeitada', 'status' => 'rejeitada']);

        $this->actingAs($admin)
            ->get(route('reports.propositions', ['stage' => 'rejeitada']))
            ->assertOk()
            ->assertSee('Matéria Rejeitada')
            ->assertDontSee('Matéria Aprovada');
    }

    public function test_proposition_report_filters_by_pautada_stage(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $session = LegislativeSession::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'number' => 1,
            'type' => 'ordinaria',
            'date' => '2026-03-10',
            'status' => 'agendada',
        ]);

        $scheduled = $this->makeProposition($institution, ['title' => 'Matéria Pautada']);
        $this->makeProposition($institution, ['title' => 'Matéria Sem Pauta']);

        SessionAgendaItem::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'session_id' => $session->id,
            'proposition_id' => $scheduled->id,
            'position' => 1,
            'appreciation_type' => 'discussao_e_votacao',
            'status' => 'pautada',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.propositions', ['stage' => 'pautada']))
            ->assertOk()
            ->assertSee('Matéria Pautada')
            ->assertDontSee('Matéria Sem Pauta');
    }

    public function test_inbox_lists_legislative_pending_items(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->makeProposition($institution, ['title' => 'Proposição em análise', 'status' => 'em_analise']);

        $commission = Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => 'Comissão de Justiça',
            'acronym' => 'CJ',
            'type' => 'permanente',
            'is_active' => true,
        ]);

        $forwarded = $this->makeProposition($institution, ['title' => 'Proposição encaminhada', 'status' => 'registrada']);

        PropositionForwarding::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'proposition_id' => $forwarded->id,
            'commission_id' => $commission->id,
            'status' => 'aguardando_parecer',
        ]);

        $this->actingAs($admin)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertSee('Pendências legislativas')
            ->assertSee('Proposições aguardando análise')
            ->assertSee('Pareceres pendentes')
            ->assertSee('Proposição em análise')
            ->assertSee('Comissão de Justiça')
            ->assertSee('Proposição encaminhada');
    }

    public function test_inbox_hides_legislative_pending_without_permission(): void
    {
        $institution = $this->makeInstitution();
        $reception = $this->makeUser($institution, 'recepcao_protocolo');

        $this->makeProposition($institution, ['title' => 'Proposição em análise', 'status' => 'em_analise']);

        $this->actingAs($reception)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertDontSee('Pendências legislativas')
            ->assertDontSee('Proposição em análise');
    }
}
