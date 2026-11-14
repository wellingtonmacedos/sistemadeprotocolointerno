<?php

namespace Tests\Feature;

use App\Enums\DestinationType;
use App\Enums\DivergenceStatus;
use App\Enums\DivergenceType;
use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\ProtocolActionType;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolDivergence;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Pacote C: divergências.
 *
 * Regra inegociável: DIVERGÊNCIA NÃO É MOVIMENTAÇÃO. Uma ocorrência não cria
 * movimentação, não marca recebimento e não altera a responsabilidade atual.
 */
class ProtocolDivergenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject = 'Protocolo Divergência'): Protocol
    {
        return app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => $subject,
            'sender_name' => 'Interessado Teste',
            'destination_sector_id' => $sector->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $user);
    }

    private function makeCommission(Institution $institution, string $name = 'Comissão de Justiça'): Commission
    {
        return Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => $name,
            'acronym' => 'CJ',
            'is_active' => true,
        ]);
    }

    public function test_divergence_is_registered_during_receipt(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $destSector->id]);

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::SECTOR, $destSector->id
        );

        $this->actingAs($receiver)
            ->post(route('protocols.receive_divergence', $movement), [
                'divergence_type' => DivergenceType::ANEXO_FALTANTE->value,
                'divergence_description' => 'Recebido sem o anexo III.',
            ])
            ->assertRedirect();

        $movement->refresh();

        // O recebimento foi confirmado normalmente.
        $this->assertSame(MovementStatus::RECEBIDO, $movement->status);
        $this->assertNotNull($movement->received_at);

        $divergence = ProtocolDivergence::withoutGlobalScopes()->first();
        $this->assertNotNull($divergence);
        $this->assertSame(DivergenceType::ANEXO_FALTANTE, $divergence->type);
        $this->assertSame(DivergenceStatus::ABERTA, $divergence->status);
        $this->assertSame('Recebido sem o anexo III.', $divergence->description);
        $this->assertSame($movement->id, $divergence->protocol_movement_id);
        $this->assertSame($destSector->id, $divergence->sector_id);
        $this->assertSame($receiver->id, $divergence->reported_by);

        // A responsabilidade passou ao destino por causa do recebimento.
        $this->assertSame($destSector->id, $protocol->fresh()->current_sector_id);
    }

    public function test_divergence_can_be_registered_after_receipt_without_changing_responsibility(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $destSector->id]);

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::SECTOR, $destSector->id
        );
        app(ProtocolService::class)->confirmReceipt($movement, $receiver);

        $protocol->refresh();
        $before = [
            'status' => $protocol->status,
            'current_sector_id' => $protocol->current_sector_id,
            'current_commission_id' => $protocol->current_commission_id,
        ];

        $this->actingAs($receiver)
            ->post(route('protocols.divergences.store', $protocol), [
                'type' => DivergenceType::INFORMACAO_INCONSISTENTE->value,
                'description' => 'Numeração de folhas divergente.',
            ])
            ->assertRedirect();

        $divergence = ProtocolDivergence::withoutGlobalScopes()->first();
        $this->assertNotNull($divergence);
        $this->assertNull($divergence->protocol_movement_id);
        // Sem vínculo informado, a ocorrência fica na responsabilidade atual.
        $this->assertSame($destSector->id, $divergence->sector_id);

        // DIVERGÊNCIA NÃO É MOVIMENTAÇÃO: a responsabilidade permanece intacta.
        $protocol->refresh();
        $this->assertSame($before, [
            'status' => $protocol->status,
            'current_sector_id' => $protocol->current_sector_id,
            'current_commission_id' => $protocol->current_commission_id,
        ]);
    }

    public function test_divergence_requires_type_and_description(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $user = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $user, $sector);

        $this->actingAs($user)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.divergences.store', $protocol), [])
            ->assertSessionHasErrors(['type', 'description']);

        $this->assertSame(0, ProtocolDivergence::withoutGlobalScopes()->count());
    }

    public function test_divergence_status_can_be_updated(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $manager = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $manager, $sector);
        $divergence = app(ProtocolService::class)->recordDivergence(
            $protocol, $manager, DivergenceType::RECEBIDO_COM_RESSALVA, 'Recebido com ressalva.'
        );

        $this->actingAs($manager)
            ->put(route('protocols.divergences.update', $divergence), [
                'status' => DivergenceStatus::EM_ANALISE->value,
            ])
            ->assertRedirect();

        $this->assertSame(DivergenceStatus::EM_ANALISE, $divergence->fresh()->status);
        $this->assertTrue($divergence->fresh()->isOpen());

        $this->actingAs($manager)
            ->put(route('protocols.divergences.update', $divergence), [
                'status' => DivergenceStatus::RESOLVIDA->value,
                'resolution_notes' => 'Anexo III juntado posteriormente.',
            ])
            ->assertRedirect();

        $divergence->refresh();
        $this->assertSame(DivergenceStatus::RESOLVIDA, $divergence->status);
        $this->assertNotNull($divergence->resolved_at);
        $this->assertFalse($divergence->isOpen());
    }

    public function test_closing_without_resolution_requires_justification(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $manager = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $manager, $sector);
        $divergence = app(ProtocolService::class)->recordDivergence(
            $protocol, $manager, DivergenceType::IDENTIFICACAO_INTERESSADO, 'Identificação incompleta.'
        );

        $this->actingAs($manager)
            ->from(route('protocols.show', $protocol))
            ->put(route('protocols.divergences.update', $divergence), [
                'status' => DivergenceStatus::ENCERRADA_SEM_RESOLUCAO->value,
            ])
            ->assertSessionHasErrors('resolution_notes');

        // A ocorrência permanece aberta: nenhum encerramento parcial foi gravado.
        $this->assertSame(DivergenceStatus::ABERTA, $divergence->fresh()->status);
        $this->assertNull($divergence->fresh()->resolved_at);

        $this->actingAs($manager)
            ->put(route('protocols.divergences.update', $divergence), [
                'status' => DivergenceStatus::ENCERRADA_SEM_RESOLUCAO->value,
                'resolution_notes' => 'Interessado não retornou para regularizar.',
            ])
            ->assertRedirect();

        $divergence->refresh();
        $this->assertSame(DivergenceStatus::ENCERRADA_SEM_RESOLUCAO, $divergence->status);
        $this->assertNotNull($divergence->resolved_at);
    }

    public function test_providencia_can_be_linked_to_a_divergence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $user = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $user, $sector);
        $divergence = app(ProtocolService::class)->recordDivergence(
            $protocol, $user, DivergenceType::ANEXO_FALTANTE, 'Falta anexo.'
        );

        $this->actingAs($user)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::SOLICITACAO_COMPLEMENTACAO->value,
                'description' => 'Solicitado o envio do anexo faltante.',
                'divergence_id' => $divergence->id,
            ])
            ->assertRedirect();

        $action = $protocol->actions()->first();
        $this->assertNotNull($action);
        $this->assertSame($divergence->id, $action->divergence_id);
        $this->assertSame($divergence->id, $action->divergence->id);
        $this->assertSame(1, $divergence->fresh()->actions()->count());
        // A providência não cria movimentação nem transfere responsabilidade.
        $this->assertNull($action->protocol_movement_id);
    }

    public function test_providencia_rejects_divergence_from_another_protocol(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $user = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocolA = $this->createProtocol($institution, $user, $sector, 'Protocolo A');
        $protocolB = $this->createProtocol($institution, $user, $sector, 'Protocolo B');

        $foreign = app(ProtocolService::class)->recordDivergence(
            $protocolB, $user, DivergenceType::OUTRO, 'Divergência do outro protocolo.'
        );

        $this->actingAs($user)
            ->from(route('protocols.show', $protocolA))
            ->post(route('protocols.actions.store', $protocolA), [
                'action_type' => ProtocolActionType::OUTROS->value,
                'description' => 'Tentativa de vínculo cruzado.',
                'divergence_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('divergence_id');

        $this->assertSame(0, $protocolA->actions()->count());
    }

    public function test_divergence_is_isolated_between_institutions(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $sectorA = $this->makeSector($institutionA, 'Protocolo');
        $sectorB = $this->makeSector($institutionB, 'Protocolo');
        $userA = $this->makeUser($institutionA, 'administrador', ['sector_id' => $sectorA->id]);
        $userB = $this->makeUser($institutionB, 'servidor_setor', ['sector_id' => $sectorB->id]);

        $protocolA = $this->createProtocol($institutionA, $userA, $sectorA);
        $divergenceA = app(ProtocolService::class)->recordDivergence(
            $protocolA, $userA, DivergenceType::OUTRO, 'Divergência da instituição A.'
        );

        // Usuário de outra instituição não enxerga o protocolo.
        $this->actingAs($userB)
            ->post(route('protocols.divergences.store', $protocolA), [
                'type' => DivergenceType::OUTRO->value,
                'description' => 'Acesso cruzado.',
            ])
            ->assertNotFound();

        // Usuário de outra instituição não enxerga nem atualiza a divergência.
        $this->actingAs($userB)
            ->put(route('protocols.divergences.update', $divergenceA), [
                'status' => DivergenceStatus::RESOLVIDA->value,
                'resolution_notes' => 'Tentativa de acesso cruzado.',
            ])
            ->assertNotFound();

        $this->assertSame(DivergenceStatus::ABERTA, $divergenceA->fresh()->status);
        $this->assertSame(1, ProtocolDivergence::withoutGlobalScopes()->count());
    }

    public function test_divergence_registration_by_unauthorized_user_is_denied(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $creator = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $outsider = $this->makeUser($institution, 'vereador');

        $protocol = $this->createProtocol($institution, $creator, $sector);

        $this->actingAs($outsider)
            ->post(route('protocols.divergences.store', $protocol), [
                'type' => DivergenceType::OUTRO->value,
                'description' => 'Tentativa indevida.',
            ])
            ->assertForbidden();

        $this->assertSame(0, ProtocolDivergence::withoutGlobalScopes()->count());
    }

    public function test_divergence_operations_are_audited(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $manager = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $manager, $sector);
        $this->actingAs($manager);

        $divergence = app(ProtocolService::class)->recordDivergence(
            $protocol, $manager, DivergenceType::ANEXO_FALTANTE, 'Falta anexo.'
        );

        app(ProtocolService::class)->updateDivergenceStatus(
            $divergence, DivergenceStatus::EM_ANALISE
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'protocol.divergence_created',
            'institution_id' => $institution->id,
            'user_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'protocol.divergence_updated',
            'institution_id' => $institution->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_divergence_records_are_compatible_with_legacy_movements(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $user = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $user, $sector);

        // Protocolo legado: existe movimentação, nenhuma divergência é presumida.
        $this->assertSame(0, $protocol->divergences()->count());

        $movement = $protocol->movements()->first();
        $this->assertNotNull($movement);

        $divergence = app(ProtocolService::class)->recordDivergence(
            $protocol, $user, DivergenceType::OUTRO, 'Ocorrência pontual.', [
                'protocol_movement_id' => $movement->id,
            ]
        );

        $this->assertSame($movement->id, $divergence->protocol_movement_id);
        // A movimentação original permanece intacta.
        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->fresh()->status);
        $this->assertNull($movement->fresh()->received_at);
    }
}
