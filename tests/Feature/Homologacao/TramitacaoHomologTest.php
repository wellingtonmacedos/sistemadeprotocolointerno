<?php

namespace Tests\Feature\Homologacao;

use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Homologação operacional — Cenário 2: tramitação ponta a ponta.
 *
 * Fluxo: Recepção -> Protocolo -> Setor Jurídico -> Secretaria -> Presidência -> Arquivamento.
 * Em cada etapa registra o usuário, data/hora, encaminhamento, confirmação de
 * recebimento, despacho e anexos. Ao final valida a timeline e que nenhum
 * anexo anterior desaparece.
 */
class TramitacaoHomologTest extends TestCase
{
    use RefreshDatabase;

    private Institution $camara;

    private Sector $protocolo;

    private Sector $juridico;

    private Sector $secretaria;

    private Sector $presidencia;

    private User $recepcao;

    private User $usuarioJuridico;

    private User $usuarioSecretaria;

    private User $usuarioPresidencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->camara = $this->makeInstitution(['name' => 'Câmara Tramitação']);

        $this->protocolo = $this->makeSector($this->camara, 'Protocolo');
        $this->juridico = $this->makeSector($this->camara, 'Jurídico');
        $this->secretaria = $this->makeSector($this->camara, 'Secretaria');
        $this->presidencia = $this->makeSector($this->camara, 'Presidência');

        $this->recepcao = $this->makeUser($this->camara, 'secretaria_diretoria', [
            'name' => 'Servidor Protocolo', 'sector_id' => $this->protocolo->id,
        ]);
        $this->usuarioJuridico = $this->makeUser($this->camara, 'secretaria_diretoria', [
            'name' => 'Servidor Jurídico', 'sector_id' => $this->juridico->id,
        ]);
        $this->usuarioSecretaria = $this->makeUser($this->camara, 'secretaria_diretoria', [
            'name' => 'Servidor Secretaria', 'sector_id' => $this->secretaria->id,
        ]);
        $this->usuarioPresidencia = $this->makeUser($this->camara, 'administrador', [
            'name' => 'Servidor Presidência', 'sector_id' => $this->presidencia->id,
        ]);
    }

    public function test_fluxo_completo_de_tramitacao_ate_o_arquivamento(): void
    {
        Storage::fake('local');

        // 1) Recepção: cadastro do protocolo com destino ao setor Protocolo.
        $this->actingAs($this->recepcao)->post(route('protocols.store'), [
            'document_type' => DocumentType::OFICIO->value,
            'subject' => 'Requerimento de tramitação completa',
            'sender_name' => 'Interessado Homologação',
            'destination_sector_id' => $this->protocolo->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ])->assertRedirect();

        $protocol = Protocol::withoutGlobalScopes()->latest('id')->firstOrFail();

        // 2) Protocolo: confirma o recebimento do movimento inicial.
        $inicial = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(MovementType::RECEBIMENTO_INICIAL, $inicial->type);
        $this->assertSame($this->recepcao->id, $inicial->from_user_id);

        $this->actingAs($this->recepcao)->post(route('protocols.receive', $inicial), [
            'description' => 'Recebido pelo protocolo.',
        ])->assertRedirect();

        $this->assertSame(MovementStatus::RECEBIDO, $inicial->fresh()->status);
        $this->assertSame($this->recepcao->id, $inicial->fresh()->received_by);
        $this->assertNotNull($inicial->fresh()->received_at);
        $this->assertSame($this->protocolo->id, $protocol->fresh()->current_sector_id);

        // Anexo inicial (Protocolo).
        $this->actingAs($this->recepcao)->post(route('protocols.attachments.store', $protocol), [
            'file' => UploadedFile::fake()->create('ANEXO-A-PROTOCOLO.pdf', 15, 'application/pdf'),
        ])->assertRedirect();

        // 3) Protocolo: despacho + anexo vinculado ao movimento.
        $this->actingAs($this->recepcao)->post(route('protocols.movement', $protocol), [
            'type' => MovementType::DESPACHO->value,
            'description' => 'Despacho do protocolo: encaminhar ao Jurídico.',
        ])->assertRedirect();

        $despacho = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame($this->recepcao->id, $despacho->from_user_id);

        $this->actingAs($this->recepcao)->post(route('protocols.attachments.store', $protocol), [
            'file' => UploadedFile::fake()->create('ANEXO-B-DESPACHO.pdf', 15, 'application/pdf'),
            'protocol_movement_id' => $despacho->id,
        ])->assertRedirect();

        // 4) Protocolo -> Jurídico.
        $this->actingAs($this->recepcao)->post(route('protocols.forward', $protocol), [
            'to_sector_id' => $this->juridico->id,
            'description' => 'Encaminhado ao Setor Jurídico.',
        ])->assertRedirect();

        $paraJuridico = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(MovementType::ENCAMINHAMENTO, $paraJuridico->type);
        $this->assertSame($this->juridico->id, $paraJuridico->to_sector_id);

        // 5) Jurídico: recebe, emite parecer com anexo e encaminha à Secretaria.
        $this->actingAs($this->usuarioJuridico)->post(route('protocols.receive', $paraJuridico), [
            'description' => 'Recebido pelo Jurídico.',
        ])->assertRedirect();

        $this->assertSame(MovementStatus::RECEBIDO, $paraJuridico->fresh()->status);
        $this->assertSame($this->usuarioJuridico->id, $paraJuridico->fresh()->received_by);

        $this->actingAs($this->usuarioJuridico)->post(route('protocols.movement', $protocol), [
            'type' => MovementType::PARECER->value,
            'description' => 'Parecer jurídico favorável.',
        ])->assertRedirect();

        $parecer = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame($this->usuarioJuridico->id, $parecer->from_user_id);

        $this->actingAs($this->usuarioJuridico)->post(route('protocols.attachments.store', $protocol), [
            'file' => UploadedFile::fake()->create('ANEXO-C-PARECER.pdf', 15, 'application/pdf'),
            'protocol_movement_id' => $parecer->id,
        ])->assertRedirect();

        $this->actingAs($this->usuarioJuridico)->post(route('protocols.forward', $protocol), [
            'to_sector_id' => $this->secretaria->id,
            'description' => 'Encaminhado à Secretaria.',
        ])->assertRedirect();

        $paraSecretaria = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        // 6) Secretaria: recebe e encaminha à Presidência.
        $this->actingAs($this->usuarioSecretaria)->post(route('protocols.receive', $paraSecretaria), [
            'description' => 'Recebido pela Secretaria.',
        ])->assertRedirect();

        $this->assertSame($this->usuarioSecretaria->id, $paraSecretaria->fresh()->received_by);

        $this->actingAs($this->usuarioSecretaria)->post(route('protocols.forward', $protocol), [
            'to_sector_id' => $this->presidencia->id,
            'description' => 'Encaminhado à Presidência.',
        ])->assertRedirect();

        $paraPresidencia = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        // 7) Presidência: recebe e arquiva.
        $this->actingAs($this->usuarioPresidencia)->post(route('protocols.receive', $paraPresidencia), [
            'description' => 'Recebido pela Presidência.',
        ])->assertRedirect();

        $this->assertSame($this->usuarioPresidencia->id, $paraPresidencia->fresh()->received_by);

        $this->actingAs($this->usuarioPresidencia)->post(route('protocols.movement', $protocol), [
            'type' => MovementType::ARQUIVAMENTO->value,
            'description' => 'Protocolo arquivado após tramitação completa.',
        ])->assertRedirect();

        // ---- Verificações finais ----

        $protocol->refresh();
        $this->assertSame(ProtocolStatus::ARQUIVADO, $protocol->status);

        $movimentos = ProtocolMovement::withoutGlobalScopes()
            ->where('protocol_id', $protocol->id)
            ->orderBy('id')
            ->get();

        // 7 movimentos: inicial, despacho, encaminhamento, parecer,
        // encaminhamento, encaminhamento, arquivamento.
        $this->assertCount(7, $movimentos);

        // Cada movimento possui usuário responsável e data/hora registrados.
        foreach ($movimentos as $movimento) {
            $this->assertNotNull($movimento->from_user_id, 'Movimento sem usuário registrado.');
            $this->assertNotNull($movimento->created_at, 'Movimento sem data/hora de criação.');
        }

        // Nenhum anexo anterior desaparece: os 3 anexos permanecem.
        $protocol->load('attachments');
        $this->assertSame(3, $protocol->attachments()->count());
        $nomes = $protocol->attachments()->pluck('original_name')->all();
        $this->assertContains('ANEXO-A-PROTOCOLO.pdf', $nomes);
        $this->assertContains('ANEXO-B-DESPACHO.pdf', $nomes);
        $this->assertContains('ANEXO-C-PARECER.pdf', $nomes);

        // A timeline apresenta todas as etapas.
        $this->actingAs($this->usuarioPresidencia)
            ->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee('Recebimento inicial')
            ->assertSee('Despacho')
            ->assertSee('Encaminhamento')
            ->assertSee('Parecer')
            ->assertSee('Arquivamento')
            // Anexos anteriores continuam visíveis na timeline.
            ->assertSee('ANEXO-A-PROTOCOLO.pdf')
            ->assertSee('ANEXO-B-DESPACHO.pdf')
            ->assertSee('ANEXO-C-PARECER.pdf');
    }
}
