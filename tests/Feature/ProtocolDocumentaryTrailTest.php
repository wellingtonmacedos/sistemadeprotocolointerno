<?php

namespace Tests\Feature;

use App\Enums\DestinationType;
use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolAction;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FASE 1 — Estrutura central de tramitação documental.
 *
 * Cobre três blocos:
 *  - Providências independentes (não criam movimentação nem transferem responsabilidade);
 *  - Comissões como destino de tramitação, com responsabilidade atribuída apenas na
 *    confirmação de recebimento (mesma transação);
 *  - Compatibilidade com os dados e fluxos de setor já existentes.
 */
class ProtocolDocumentaryTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function makeCommission(Institution $institution, string $name = 'Comissão de Constituição e Justiça'): Commission
    {
        return Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => $name,
            'acronym' => strtoupper(Str::random(6)),
            'type' => 'permanente',
            'is_active' => true,
            'display_order' => 0,
        ]);
    }

    private function createProtocol(Institution $institution, User $user, ?Sector $sector = null, string $subject = 'Protocolo FASE 1'): Protocol
    {
        return app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => $subject,
            'sender_name' => 'Interessado Teste',
            'destination_sector_id' => $sector?->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $user);
    }

    /**
     * Protocolo cuja responsabilidade atual é um setor (recebimento inicial confirmado).
     */
    private function protocolResponsibleAtSector(Institution $institution, User $user, Sector $sector): Protocol
    {
        $protocol = $this->createProtocol($institution, $user, $sector);

        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        app(ProtocolService::class)->confirmReceipt($movement, $user);

        return $protocol->refresh();
    }

    // =====================================================================
    // Bloco 1 — Providências (9)
    // =====================================================================

    public function test_providencia_is_registered_and_inherits_current_sector(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::CONFERENCIA_DOCUMENTAL->value,
                'description' => 'Conferência documental realizada.',
            ])
            ->assertRedirect();

        $action = ProtocolAction::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($protocol->id, $action->protocol_id);
        $this->assertSame(ProtocolActionType::CONFERENCIA_DOCUMENTAL, $action->action_type);
        $this->assertSame(ProtocolActionStatus::REGISTRADA, $action->status);
        $this->assertSame($admin->id, $action->user_id);
        // Local herdado da responsabilidade atual (setor).
        $this->assertSame($sector->id, $action->sector_id);
        $this->assertNull($action->commission_id);
    }

    public function test_providencia_does_not_create_movement_or_transfer_responsibility(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $movementsBefore = ProtocolMovement::withoutGlobalScopes()->where('protocol_id', $protocol->id)->count();

        $this->actingAs($admin)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::DESPACHO->value,
                'description' => 'Despacho interno sem encaminhamento.',
            ])
            ->assertRedirect();

        $movementsAfter = ProtocolMovement::withoutGlobalScopes()->where('protocol_id', $protocol->id)->count();

        // Nenhuma movimentação nova e responsabilidade inalterada.
        $this->assertSame($movementsBefore, $movementsAfter);
        $this->assertSame($sector->id, $protocol->fresh()->current_sector_id);
        $this->assertNull($protocol->fresh()->current_commission_id);
    }

    public function test_providencia_inherits_current_commission_when_registered_by_commission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        // Encaminha à comissão e confirma o recebimento.
        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);
        $incoming = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        app(ProtocolService::class)->confirmReceipt($incoming, $admin);

        $this->actingAs($admin)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::EMISSAO_PARECER->value,
                'description' => 'Parecer emitido pela comissão.',
                'result' => 'Parecer favorável.',
            ])
            ->assertRedirect();

        $action = ProtocolAction::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($commission->id, $action->commission_id);
        $this->assertNull($action->sector_id);
    }

    public function test_providencia_rejects_invalid_action_type(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => 'tipo_inexistente',
                'description' => 'Descrição qualquer.',
            ])
            ->assertSessionHasErrors('action_type');
    }

    public function test_providencia_requires_description(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::OUTROS->value,
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_providencia_rejects_both_sector_and_commission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::ANALISE_TECNICA->value,
                'description' => 'Local ambíguo.',
                'sector_id' => $sector->id,
                'commission_id' => $commission->id,
            ])
            ->assertSessionHasErrors('commission_id');
    }

    public function test_providencia_rejects_movement_from_another_protocol(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocolA = $this->protocolResponsibleAtSector($institution, $admin, $sector);
        $protocolB = $this->createProtocol($institution, $admin, $sector, 'Segundo protocolo');

        $movementB = ProtocolMovement::withoutGlobalScopes()
            ->where('protocol_id', $protocolB->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocolA))
            ->post(route('protocols.actions.store', $protocolA), [
                'action_type' => ProtocolActionType::JUNTADA_DOCUMENTOS->value,
                'description' => 'Tentativa de vínculo cruzado.',
                'protocol_movement_id' => $movementB->id,
            ])
            ->assertSessionHasErrors('protocol_movement_id');
    }

    public function test_providencia_requires_create_permission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $vereador = $this->makeUser($institution, 'vereador');

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($vereador)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::OUTROS->value,
                'description' => 'Sem permissão de criação.',
            ])
            ->assertForbidden();
    }

    public function test_providencia_can_be_linked_to_a_movement(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $movement = ProtocolMovement::withoutGlobalScopes()
            ->where('protocol_id', $protocol->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('protocols.actions.store', $protocol), [
                'action_type' => ProtocolActionType::PREPARACAO_ENCAMINHAMENTO->value,
                'description' => 'Preparação vinculada ao recebimento inicial.',
                'protocol_movement_id' => $movement->id,
            ])
            ->assertRedirect();

        $action = ProtocolAction::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($movement->id, $action->protocol_movement_id);
    }

    // =====================================================================
    // Bloco 2 — Comissões como destino (10)
    // =====================================================================

    public function test_forward_to_commission_succeeds(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
                'to_commission_id' => $commission->id,
                'description' => 'Encaminhado à comissão.',
            ])
            ->assertRedirect();

        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($commission->id, $movement->to_commission_id);
        $this->assertNull($movement->to_sector_id);
        $this->assertSame(DestinationType::COMMISSION, $movement->destination_type);
        $this->assertSame(MovementStatus::AGUARDANDO_RECEBIMENTO, $movement->status);
    }

    public function test_forward_to_commission_requires_permission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $servidor = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        // servidor_setor possui protocols.forward, mas não protocols.dispatch_commission.
        $this->actingAs($servidor)
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
                'to_commission_id' => $commission->id,
            ])
            ->assertForbidden();
    }

    public function test_forwarded_protocol_has_no_responsible_until_receipt(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
                'to_commission_id' => $commission->id,
            ])
            ->assertRedirect();

        $protocol->refresh();

        // Destino pendente não é responsável atual.
        $this->assertNull($protocol->current_sector_id);
        $this->assertNull($protocol->current_commission_id);
        $this->assertSame(ProtocolStatus::ENCAMINHADO, $protocol->status);
    }

    public function test_commission_receipt_assigns_responsibility_in_same_transaction(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);
        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('protocols.receive', $movement))
            ->assertRedirect();

        $protocol->refresh();

        $this->assertSame(MovementStatus::RECEBIDO, $movement->fresh()->status);
        $this->assertSame($commission->id, $protocol->current_commission_id);
        $this->assertNull($protocol->current_sector_id);
        $this->assertSame(ProtocolStatus::RECEBIDO, $protocol->status);
    }

    public function test_sector_receipt_assigns_sector_responsibility(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->assertSame($sector->id, $protocol->current_sector_id);
        $this->assertNull($protocol->current_commission_id);
    }

    public function test_commission_receipt_requires_permission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $servidor = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);
        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);
        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        // servidor_setor possui protocols.receive, mas não protocols.receive_commission.
        $this->actingAs($servidor)
            ->post(route('protocols.receive', $movement))
            ->assertForbidden();
    }

    public function test_forward_rejects_two_destinations_at_once(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
                'to_sector_id' => $sector->id,
                'to_commission_id' => $commission->id,
            ])
            ->assertSessionHasErrors('to_commission_id');
    }

    public function test_forward_to_commission_without_commission_is_rejected(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
            ])
            ->assertSessionHasErrors('to_commission_id');
    }

    public function test_commission_movement_appears_in_inbox_for_authorized_user(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);
        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);

        $this->actingAs($admin)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertSee($commission->name);
    }

    public function test_commission_is_not_responsible_before_receipt_but_is_after(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);

        // Antes do recebimento a comissão não é responsável pelo protocolo.
        $this->assertFalse($commission->protocols()->whereKey($protocol->id)->exists());

        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();
        app(ProtocolService::class)->confirmReceipt($movement, $admin);

        // Após o recebimento passa a ser responsável.
        $this->assertTrue($commission->protocols()->whereKey($protocol->id)->exists());
    }

    public function test_commission_labels_on_movement(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $commission = $this->makeCommission($institution, 'Comissão de Finanças');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        app(ProtocolService::class)->forward($protocol, $admin, DestinationType::COMMISSION, $commission->id);
        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($sector->name, $movement->originLabel());
        $this->assertSame($commission->name, $movement->destinationLabel());
        $this->assertSame(DestinationType::COMMISSION, $movement->destinationType());
    }

    // =====================================================================
    // Bloco 3 — Compatibilidade (6)
    // =====================================================================

    public function test_legacy_sector_only_movement_derives_sector_type(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        // Simula registro "antigo": apenas setor, sem colunas novas preenchidas.
        $legacy = ProtocolMovement::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'from_sector_id' => null,
            'to_sector_id' => $sector->id,
            'type' => MovementType::ENCAMINHAMENTO,
            'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
        ]);

        $this->assertNull($legacy->destination_type);
        $this->assertNull($legacy->to_commission_id);
        $this->assertSame(DestinationType::SECTOR, $legacy->destinationType());
        $this->assertSame($sector->name, $legacy->destinationLabel());
        $this->assertSame('Origem externa', $legacy->originLabel());
    }

    public function test_traditional_sector_forward_still_works(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        // Encaminhamento tradicional (sem destination_type / comissão).
        $this->actingAs($admin)
            ->post(route('protocols.forward', $protocol), [
                'to_sector_id' => $sector->id,
                'description' => 'Encaminhamento tradicional ao setor.',
            ])
            ->assertRedirect();

        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($sector->id, $movement->to_sector_id);
        $this->assertNull($movement->to_commission_id);
        $this->assertSame(DestinationType::SECTOR, $movement->destinationType());
    }

    public function test_existing_protocol_records_remain_readable_without_commission_data(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institution, $admin, $sector);

        $this->assertNull($protocol->current_commission_id);
        $this->assertTrue($protocol->actions()->doesntExist());

        // A tela de detalhes continua renderizando sem dados de comissão.
        $this->actingAs($admin)
            ->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee($protocol->formatted_number);
    }

    public function test_cannot_forward_to_commission_of_another_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $sector = $this->makeSector($institutionA, 'Protocolo');
        $commissionB = $this->makeCommission($institutionB, 'Comissão da Câmara B');
        $adminA = $this->makeUser($institutionA, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->protocolResponsibleAtSector($institutionA, $adminA, $sector);

        $this->actingAs($adminA)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.forward', $protocol), [
                'destination_type' => DestinationType::COMMISSION->value,
                'to_commission_id' => $commissionB->id,
            ])
            ->assertSessionHasErrors('to_commission_id');
    }

    public function test_providencia_store_is_isolated_between_institutions(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $sectorB = $this->makeSector($institutionB, 'Protocolo');
        $adminA = $this->makeUser($institutionA, 'administrador');
        $adminB = $this->makeUser($institutionB, 'administrador', ['sector_id' => $sectorB->id]);

        $protocolB = $this->protocolResponsibleAtSector($institutionB, $adminB, $sectorB);

        // Usuário da instituição A não acessa o protocolo da instituição B.
        $this->actingAs($adminA)
            ->post(route('protocols.actions.store', $protocolB), [
                'action_type' => ProtocolActionType::OUTROS->value,
                'description' => 'Tentativa indevida de acesso cruzado.',
            ])
            ->assertNotFound();
    }
}
