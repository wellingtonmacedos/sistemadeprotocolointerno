<?php

namespace Tests\Feature;

use App\Enums\DestinationType;
use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Enums\RefusalReason;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Pacote B: recusa de recebimento.
 *
 * Regra inegociável: RECUSA NÃO É RECEBIMENTO. A responsabilidade atual
 * permanece com o remetente e a movimentação original nunca é apagada.
 */
class ProtocolRefusalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject = 'Protocolo Recusa'): Protocol
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

    public function test_refusal_with_reason_is_recorded(): void
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
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::DESTINO_INCORRETO->value,
                'refusal_notes' => 'Documento pertence à Secretaria de Obras.',
            ])
            ->assertRedirect();

        $movement->refresh();

        $this->assertSame(MovementStatus::RECUSADO, $movement->status);
        $this->assertSame(RefusalReason::DESTINO_INCORRETO, $movement->refusal_reason);
        $this->assertSame('Documento pertence à Secretaria de Obras.', $movement->refusal_notes);
        $this->assertNotNull($movement->refused_at);
        $this->assertSame($receiver->id, $movement->refused_by);
        $this->assertSame('Destino incorreto', $movement->refusalLabel());
    }

    public function test_refusal_requires_reason_and_description(): void
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
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.refuse', $movement), [])
            ->assertSessionHasErrors(['refusal_reason', 'refusal_notes']);

        // A movimentação permanece pendente: nenhuma recusa parcial foi gravada.
        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->fresh()->status);
    }

    public function test_refusal_does_not_change_current_responsibility(): void
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

        $protocol->refresh();
        $before = [
            'status' => $protocol->status,
            'current_sector_id' => $protocol->current_sector_id,
            'current_commission_id' => $protocol->current_commission_id,
        ];

        app(ProtocolService::class)->refuseReceipt($movement, $receiver, RefusalReason::DOCUMENTO_INCOMPLETO, 'Faltam anexos.');

        $protocol->refresh();

        $this->assertSame($before, [
            'status' => $protocol->status,
            'current_sector_id' => $protocol->current_sector_id,
            'current_commission_id' => $protocol->current_commission_id,
        ]);
        // O destino recusado nunca se torna o responsável atual.
        $this->assertNotSame($destSector->id, $protocol->current_sector_id);
        $this->assertNull($protocol->current_commission_id);
        $this->assertNotSame(ProtocolStatus::RECEBIDO, $protocol->status);
    }

    public function test_refused_movement_is_not_marked_as_received(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $destSector->id]);

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::SECTOR, $destSector->id,
            'Encaminhamento original preservado.'
        );

        app(ProtocolService::class)->refuseReceipt($movement, $receiver, RefusalReason::ENCAMINHAMENTO_INDEVIDO, 'Fora do fluxo.');

        $movement->refresh();

        $this->assertSame(MovementStatus::RECUSADO, $movement->status);
        $this->assertNull($movement->received_at);
        $this->assertNull($movement->received_by);
        $this->assertFalse($movement->isCompleted());
        $this->assertFalse($movement->isPending());
        $this->assertTrue($movement->isRefused());
        // A movimentação original continua existindo e consultável.
        $this->assertSame('Encaminhamento original preservado.', $movement->description);
        $this->assertSame($destSector->id, $movement->to_sector_id);
    }

    public function test_refusal_by_unauthorized_user_is_denied(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $outsider = $this->makeUser($institution, 'vereador');

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::SECTOR, $destSector->id
        );

        $this->actingAs($outsider)
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::OUTRO->value,
                'refusal_notes' => 'Tentativa indevida.',
            ])
            ->assertForbidden();

        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->fresh()->status);
    }

    public function test_refusal_denied_for_user_from_another_sector(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $otherSector = $this->makeSector($institution, 'Obras');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $wrongReceiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $otherSector->id]);

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::SECTOR, $destSector->id
        );

        $this->actingAs($wrongReceiver)
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::OUTRO->value,
                'refusal_notes' => 'Setor errado.',
            ])
            ->assertForbidden();

        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->fresh()->status);
    }

    public function test_refusal_is_audited(): void
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

        $this->actingAs($receiver);

        app(ProtocolService::class)->refuseReceipt($movement, $receiver, RefusalReason::DIVERGENCIA_IDENTIFICACAO, 'CPF divergente.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'protocol.refused',
            'institution_id' => $institution->id,
            'user_id' => $receiver->id,
        ]);
    }

    public function test_commission_destination_can_be_refused(): void
    {
        $institution = $this->makeInstitution();
        $originSector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $originSector->id]);
        $receiver = $this->makeUser($institution, 'presidente');

        $protocol = $this->createProtocol($institution, $origin, $originSector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::COMMISSION, $commission->id
        );

        $this->actingAs($receiver)
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::DOCUMENTACAO_OBRIGATORIA_AUSENTE->value,
                'refusal_notes' => 'Falta o parecer da assessoria.',
            ])
            ->assertRedirect();

        $movement->refresh();

        $this->assertSame(MovementStatus::RECUSADO, $movement->status);
        $this->assertSame(RefusalReason::DOCUMENTACAO_OBRIGATORIA_AUSENTE, $movement->refusal_reason);
        $this->assertNull($protocol->fresh()->current_commission_id);
    }

    public function test_already_received_movement_cannot_be_refused(): void
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

        $this->actingAs($receiver)
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::OUTRO->value,
                'refusal_notes' => 'Tentativa após recebimento.',
            ])
            ->assertForbidden();

        $this->assertSame(MovementStatus::RECEBIDO, $movement->fresh()->status);
    }

    public function test_refusal_is_isolated_between_institutions(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $sectorA = $this->makeSector($institutionA, 'Protocolo');
        $destA = $this->makeSector($institutionA, 'Financeiro');
        $sectorB = $this->makeSector($institutionB, 'Protocolo');
        $originA = $this->makeUser($institutionA, 'administrador', ['sector_id' => $sectorA->id]);
        $userB = $this->makeUser($institutionB, 'servidor_setor', ['sector_id' => $sectorB->id]);

        $protocol = $this->createProtocol($institutionA, $originA, $sectorA);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $originA, DestinationType::SECTOR, $destA->id
        );

        // Usuário de outra instituição não pode enxergar nem recusar a movimentação.
        $this->actingAs($userB)
            ->post(route('protocols.refuse', $movement), [
                'refusal_reason' => RefusalReason::OUTRO->value,
                'refusal_notes' => 'Acesso cruzado.',
            ])
            ->assertNotFound();

        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->fresh()->status);
    }
}
