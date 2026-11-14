<?php

namespace Tests\Feature;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineSituation;
use App\Enums\DeadlineSource;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\ProtocolPriority;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Pacote A: prazos nas movimentações.
 */
class ProtocolDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject = 'Protocolo Prazo'): Protocol
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

    private function latestMovement(Protocol $protocol): ProtocolMovement
    {
        return ProtocolMovement::withoutGlobalScopes()
            ->where('protocol_id', $protocol->id)
            ->latest('id')
            ->firstOrFail();
    }

    public function test_forward_without_deadline_marks_sem_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $sector->id
        );

        $this->assertNull($movement->due_at);
        $this->assertFalse($movement->hasDeadline());
        $this->assertSame(DeadlineSituation::SEM_PRAZO, $movement->deadlineSituation());
        $this->assertSame('Sem prazo', $movement->deadlineLabel());
    }

    public function test_http_forward_with_calendar_deadline_computes_due_at(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        // Segunda-feira, 02/03/2026.
        $start = Carbon::parse('2026-03-02 09:00:00');
        $this->assertTrue($start->isMonday());
        $this->travelTo($start);

        $this->actingAs($admin)
            ->post(route('protocols.forward', $protocol), [
                'to_sector_id' => $sector->id,
                'deadline_days' => 5,
                'deadline_type' => DeadlineType::ADMINISTRATIVO->value,
                'deadline_mode' => DeadlineMode::CORRIDOS->value,
                'deadline_notes' => 'Prazo definido pela chefia.',
            ])
            ->assertRedirect();

        $movement = $this->latestMovement($protocol);

        $this->assertTrue($movement->due_at->equalTo(Carbon::parse('2026-03-07 09:00:00')));
        $this->assertSame(5, $movement->deadline);
        $this->assertSame(DeadlineType::ADMINISTRATIVO, $movement->deadline_type);
        $this->assertSame(DeadlineMode::CORRIDOS, $movement->deadline_mode);
        $this->assertSame(DeadlineSource::MANUAL, $movement->deadline_source);
        $this->assertSame('Prazo definido pela chefia.', $movement->deadline_notes);
    }

    public function test_http_forward_with_business_days_deadline_skips_weekend(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $this->travelTo(Carbon::parse('2026-03-02 09:00:00'));

        $this->actingAs($admin)
            ->post(route('protocols.forward', $protocol), [
                'to_sector_id' => $sector->id,
                'deadline_days' => 5,
                'deadline_type' => DeadlineType::REGIMENTAL->value,
                'deadline_mode' => DeadlineMode::UTEIS->value,
            ])
            ->assertRedirect();

        $movement = $this->latestMovement($protocol);

        // 5 dias úteis a partir de 02/03 (segunda) = 09/03 (segunda).
        $this->assertTrue($movement->due_at->equalTo(Carbon::parse('2026-03-09 09:00:00')));
        $this->assertSame(DeadlineMode::UTEIS, $movement->deadline_mode);
    }

    public function test_deadline_type_is_required_when_deadline_days_informed(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $this->actingAs($admin)
            ->from(route('protocols.show', $protocol))
            ->post(route('protocols.forward', $protocol), [
                'to_sector_id' => $sector->id,
                'deadline_days' => 5,
            ])
            ->assertSessionHasErrors('deadline_type');
    }

    public function test_overdue_pending_movement_is_vencido(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = ProtocolMovement::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'to_sector_id' => $sector->id,
            'type' => MovementType::ENCAMINHAMENTO,
            'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
            'deadline' => 5,
            'due_at' => now()->subDay(),
            'deadline_type' => DeadlineType::ADMINISTRATIVO,
            'deadline_mode' => DeadlineMode::CORRIDOS,
        ]);

        $this->assertSame(DeadlineSituation::VENCIDO, $movement->deadlineSituation());
        $this->assertTrue($movement->isOverdue());
    }

    public function test_near_due_movement_is_proximo_vencimento(): void
    {
        $this->travelTo(Carbon::parse('2026-03-02 09:00:00'));

        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = ProtocolMovement::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'to_sector_id' => $sector->id,
            'type' => MovementType::ENCAMINHAMENTO,
            'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
            'due_at' => now()->addDay(),
            'deadline_type' => DeadlineType::ADMINISTRATIVO,
            'deadline_mode' => DeadlineMode::CORRIDOS,
        ]);

        $this->assertSame(DeadlineSituation::PROXIMO_VENCIMENTO, $movement->deadlineSituation());
        $this->assertTrue($movement->isNearDue());
        $this->assertFalse($movement->isOverdue());
    }

    public function test_completed_movement_within_deadline_is_concluido_no_prazo(): void
    {
        $this->travelTo(Carbon::parse('2026-03-02 09:00:00'));

        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $sector->id,
            null, MovementType::ENCAMINHAMENTO, null, 5, DeadlineType::ADMINISTRATIVO, DeadlineMode::CORRIDOS
        );

        app(ProtocolService::class)->confirmReceipt($movement, $admin);

        $this->assertSame(DeadlineSituation::CONCLUIDO_NO_PRAZO, $movement->fresh()->deadlineSituation());
    }

    public function test_completed_movement_after_deadline_is_concluido_apos_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = ProtocolMovement::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'to_sector_id' => $sector->id,
            'type' => MovementType::ENCAMINHAMENTO,
            'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
            'due_at' => now()->subDays(2),
            'deadline_type' => DeadlineType::ADMINISTRATIVO,
            'deadline_mode' => DeadlineMode::CORRIDOS,
        ]);

        app(ProtocolService::class)->confirmReceipt($movement, $admin);

        $this->assertSame(DeadlineSituation::CONCLUIDO_APOS_PRAZO, $movement->fresh()->deadlineSituation());
    }

    public function test_protocol_deadline_situation_follows_current_movement(): void
    {
        $this->travelTo(Carbon::parse('2026-03-02 09:00:00'));

        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $movement = app(ProtocolService::class)->forward(
            $protocol, $admin, DestinationType::SECTOR, $sector->id,
            null, MovementType::ENCAMINHAMENTO, null, 5, DeadlineType::ADMINISTRATIVO, DeadlineMode::CORRIDOS
        );

        $this->assertNotNull($protocol->fresh()->currentDueAt());
        $this->assertSame(DeadlineSituation::NO_PRAZO, $protocol->fresh()->deadlineSituation());

        app(ProtocolService::class)->confirmReceipt($movement, $admin);

        $this->assertSame(DeadlineSituation::CONCLUIDO_NO_PRAZO, $protocol->fresh()->deadlineSituation());
    }

    public function test_legacy_movements_without_deadline_remain_compatible(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);
        $protocol = $this->createProtocol($institution, $admin, $sector);

        $legacy = ProtocolMovement::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'to_sector_id' => $sector->id,
            'type' => MovementType::ENCAMINHAMENTO,
            'status' => MovementStatus::AGUARDANDO_RECEBIMENTO,
        ]);

        $this->assertFalse($legacy->hasDeadline());
        $this->assertNull($legacy->deadline_type);
        $this->assertSame(DeadlineSituation::SEM_PRAZO, $legacy->deadlineSituation());
    }
}
