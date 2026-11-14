<?php

namespace Tests\Feature;

use App\Enums\DestinationType;
use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolPriority;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Models\ProtocolMovementCorrection;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Pacote D: retificação de movimentações.
 *
 * Regra inegociável: RETIFICAÇÃO NÃO É EXCLUSÃO. O registro original é
 * preservado e consultável; apenas campos revisáveis podem ser alterados;
 * datas e usuário originais permanecem intocados; alterar o destino exige
 * autorização elevada.
 */
class ProtocolMovementCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject = 'Protocolo Retificação'): Protocol
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

    public function test_movement_can_be_corrected_with_reason(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Erro material na descrição.',
                'description' => 'Descrição retificada.',
            ])
            ->assertRedirect();

        $movement->refresh();

        $this->assertSame('Descrição retificada.', $movement->description);
        $this->assertDatabaseHas('protocol_movement_corrections', [
            'protocol_movement_id' => $movement->id,
            'institution_id' => $institution->id,
            'user_id' => $admin->id,
            'reason' => 'Erro material na descrição.',
        ]);
    }

    public function test_correction_preserves_original_data(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        app(ProtocolService::class)->correctMovement(
            $movement, $admin, 'Erro material.', ['description' => 'Descrição retificada.']
        );

        $correction = ProtocolMovementCorrection::where('protocol_movement_id', $movement->id)->firstOrFail();

        // O valor anterior continua preservado no histórico.
        $this->assertSame('Descrição original.', $correction->original_data['description']);
        $this->assertSame('Descrição retificada.', $correction->corrected_data['description']);
    }

    public function test_correction_history_is_consultable(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        app(ProtocolService::class)->correctMovement(
            $movement, $admin, 'Erro material.', ['description' => 'Descrição retificada.']
        );

        $protocol->refresh();

        $this->assertCount(1, $protocol->movementCorrections);
        $this->assertCount(1, $movement->fresh()->corrections);

        $rows = $protocol->movementCorrections->first()->comparisonRows();
        $this->assertNotEmpty($rows);
        $this->assertSame('Descrição original.', $rows[0]['original']);
        $this->assertSame('Descrição retificada.', $rows[0]['corrected']);
    }

    public function test_correction_requires_reason(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'description' => 'Tentativa sem motivo.',
            ])
            ->assertSessionHasErrors('reason');

        // Nenhuma retificação parcial foi gravada e o original permanece intacto.
        $this->assertDatabaseCount('protocol_movement_corrections', 0);
        $this->assertSame('Descrição original.', $movement->fresh()->description);
    }

    public function test_correction_requires_at_least_one_field(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id
        );

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Motivo sem alteração efetiva.',
            ])
            ->assertSessionHasErrors('changes');

        $this->assertDatabaseCount('protocol_movement_corrections', 0);
    }

    public function test_correction_denied_for_unauthorized_user(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $outsider = $this->makeUser($institution, 'vereador');

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $this->actingAs($outsider)
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Tentativa indevida.',
                'description' => 'Alteração indevida.',
            ])
            ->assertForbidden();

        $this->assertSame('Descrição original.', $movement->fresh()->description);
        $this->assertDatabaseCount('protocol_movement_corrections', 0);
    }

    public function test_correction_is_isolated_between_institutions(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $sectorA = $this->makeSector($institutionA, 'Protocolo');
        $destA = $this->makeSector($institutionA, 'Financeiro');
        $sectorB = $this->makeSector($institutionB, 'Protocolo');
        $adminA = $this->makeUser($institutionA, 'administrador', ['sector_id' => $sectorA->id]);
        $adminB = $this->makeUser($institutionB, 'administrador', ['sector_id' => $sectorB->id]);

        $protocol = $this->createProtocol($institutionA, $adminA, $sectorA);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $adminA, DestinationType::SECTOR, $destA->id, 'Descrição original.'
        );

        // Usuário de outra instituição não enxerga nem retifica a movimentação.
        $this->actingAs($adminB)
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Acesso cruzado.',
                'description' => 'Alteração cruzada.',
            ])
            ->assertNotFound();

        $this->assertSame('Descrição original.', $movement->fresh()->description);
        $this->assertDatabaseCount('protocol_movement_corrections', 0);
    }

    public function test_correction_preserves_original_dates_and_user(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $original = $movement->fresh();
        $originalCreatedAt = $original->created_at->toDateTimeString();
        $originalFromUser = $original->from_user_id;

        app(ProtocolService::class)->correctMovement(
            $movement, $admin, 'Erro material.', ['description' => 'Descrição retificada.']
        );

        $movement->refresh();

        // Datas e usuário originais permanecem intocados: a retificação não
        // reescreve a autoria nem o momento do encaminhamento.
        $this->assertSame($originalCreatedAt, $movement->created_at->toDateTimeString());
        $this->assertSame($originalFromUser, $movement->from_user_id);
    }

    public function test_correction_does_not_delete_the_movement(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $movementId = $movement->id;

        app(ProtocolService::class)->correctMovement(
            $movement, $admin, 'Erro material.', ['description' => 'Descrição retificada.']
        );
        app(ProtocolService::class)->correctMovement(
            $movement->fresh(), $admin, 'Segunda revisão.', ['description' => 'Descrição final.']
        );

        // A movimentação continua existindo e cada retificação é um novo registro.
        $this->assertDatabaseHas('protocol_movements', ['id' => $movementId]);
        $this->assertDatabaseCount('protocol_movement_corrections', 2);
        $this->assertSame('Descrição final.', $movement->fresh()->description);
    }

    public function test_non_correctable_fields_are_ignored(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $otherUser = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $destSector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $originalFromUser = $movement->from_user_id;

        // Campos fora da whitelist (autoria, datas, recusa) não podem ser retificados.
        app(ProtocolService::class)->correctMovement($movement, $admin, 'Tentativa.', [
            'description' => 'Descrição retificada.',
            'from_user_id' => $otherUser->id,
            'received_at' => now(),
        ]);

        $correction = ProtocolMovementCorrection::where('protocol_movement_id', $movement->id)->firstOrFail();

        $this->assertSame($originalFromUser, $movement->fresh()->from_user_id);
        $this->assertNull($movement->fresh()->received_at);
        $this->assertArrayNotHasKey('from_user_id', $correction->corrected_data);
        $this->assertArrayNotHasKey('received_at', $correction->corrected_data);
    }

    public function test_destination_change_requires_elevated_authorization(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $newSector = $this->makeSector($institution, 'Obras');
        $secretaria = $this->makeUser($institution, 'secretaria_diretoria', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $secretaria, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $secretaria, DestinationType::SECTOR, $destSector->id
        );

        // secretaria_diretoria possui `protocols.movements.correct` mas não a
        // permissão elevada `protocols.movements.correct_destination`.
        $this->actingAs($secretaria)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Mudança de destino.',
                'destination' => 'sector:'.$newSector->id,
            ])
            ->assertForbidden();

        $this->assertSame($destSector->id, $movement->fresh()->to_sector_id);
    }

    public function test_destination_change_is_applied_by_authorized_user(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $newSector = $this->makeSector($institution, 'Obras');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id
        );

        // Após o recebimento, a movimentação é a responsável vigente.
        app(ProtocolService::class)->confirmReceipt($movement, $admin);
        $this->assertSame($destSector->id, $protocol->fresh()->current_sector_id);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Encaminhamento para o setor incorreto.',
                'destination' => 'sector:'.$newSector->id,
            ])
            ->assertRedirect();

        $movement->refresh();

        $this->assertSame($newSector->id, $movement->to_sector_id);
        $this->assertNull($movement->to_commission_id);
        $this->assertSame(DestinationType::SECTOR, $movement->destination_type);
        // A responsabilidade atual acompanha a retificação da movimentação vigente.
        $this->assertSame($newSector->id, $protocol->fresh()->current_sector_id);
        $this->assertNull($protocol->fresh()->current_commission_id);
    }

    public function test_destination_change_to_commission_keeps_sector_unset(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $commission = $this->makeCommission($institution);
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id
        );

        app(ProtocolService::class)->confirmReceipt($movement, $admin);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.movements.correct', $movement), [
                'reason' => 'Competência é da comissão.',
                'destination' => 'commission:'.$commission->id,
            ])
            ->assertRedirect();

        $movement->refresh();

        $this->assertSame($commission->id, $movement->to_commission_id);
        $this->assertNull($movement->to_sector_id);
        $this->assertSame(DestinationType::COMMISSION, $movement->destination_type);
        $this->assertSame($commission->id, $protocol->fresh()->current_commission_id);
        $this->assertNull($protocol->fresh()->current_sector_id);
    }

    public function test_correction_is_audited(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destSector = $this->makeSector($institution, 'Financeiro');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $admin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $destSector->id, 'Descrição original.'
        );

        $this->actingAs($admin);

        app(ProtocolService::class)->correctMovement(
            $movement, $admin, 'Erro material.', ['description' => 'Descrição retificada.']
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'protocol.movement_corrected',
            'institution_id' => $institution->id,
            'user_id' => $admin->id,
        ]);
    }
}
