<?php

namespace Tests\Feature;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineSituation;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Enums\ProtocolPriority;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolAction;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FASE 2 — Pacote E: tempo de permanência e situações de prazo (§7).
 *
 * Regras inegociáveis:
 * - movimentação aguardando recebimento não conta permanência;
 * - movimentação recusada não inicia contagem no destino recusado;
 * - andamento no mesmo destino não encerra a permanência;
 * - conclusão de providência não encerra o prazo da movimentação.
 */
class ProtocolPermanenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector): Protocol
    {
        return app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => 'Protocolo Permanência',
            'sender_name' => 'Interessado Teste',
            'destination_sector_id' => $sector->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $user);
    }

    public function test_pending_movement_has_no_permanence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);

        $this->assertFalse($movement->hasPermanence());
        $this->assertNull($movement->permanenceStartedAt());
        $this->assertNull($movement->permanenceEndedAt());
        $this->assertNull($movement->permanenceDuration());
    }

    public function test_refused_movement_has_no_permanence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);

        app(ProtocolService::class)->refuseReceipt($movement, $origin, \App\Enums\RefusalReason::OUTRO, 'Recusado.');

        $movement->refresh();

        $this->assertFalse($movement->hasPermanence());
        $this->assertNull($movement->permanenceDuration());
    }

    public function test_received_movement_starts_and_measures_permanence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);

        app(ProtocolService::class)->confirmReceipt($movement, $origin);
        $this->travel(3)->days();

        $movement->refresh();

        $this->assertTrue($movement->hasPermanence());
        $this->assertSame($movement->received_at->timestamp, $movement->permanenceStartedAt()->timestamp);
        $this->assertSame('3 d', $movement->permanenceDuration());
    }

    public function test_permanence_ends_at_next_effective_transfer(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destA = $this->makeSector($institution, 'Financeiro');
        $destB = $this->makeSector($institution, 'Obras');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);

        $first = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $destA->id);
        app(ProtocolService::class)->confirmReceipt($first, $origin);

        $this->travel(2)->days();

        $second = app(ProtocolService::class)->forward($protocol->fresh(), $origin, DestinationType::SECTOR, $destB->id);

        $first->refresh();

        // A permanência encerra no momento da próxima transferência efetiva.
        $this->assertSame(
            $second->created_at->toDateTimeString(),
            $first->permanenceEndedAt()->toDateTimeString(),
        );
        $this->assertSame('2 d', $first->permanenceDuration());
    }

    public function test_andamento_at_same_destination_does_not_end_permanence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);
        app(ProtocolService::class)->confirmReceipt($movement, $origin);

        // Andamento no mesmo destino: não é transferência efetiva.
        app(ProtocolService::class)->register($protocol->fresh(), $origin, MovementType::DESPACHO, 'Andamento interno.');

        $this->travel(1)->day();

        $movement->refresh();

        $this->assertNull($movement->followingTransfer());
        $this->assertSame(now()->toDateTimeString(), $movement->permanenceEndedAt()->toDateTimeString());
        $this->assertSame('1 d', $movement->permanenceDuration());
    }

    public function test_permanence_ends_at_protocol_closure(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);
        app(ProtocolService::class)->confirmReceipt($movement, $origin);

        $this->travel(1)->day();

        $closing = app(ProtocolService::class)->register($protocol->fresh(), $origin, MovementType::CONCLUSAO, 'Concluído.');

        $movement->refresh();

        $this->assertSame(
            $closing->created_at->toDateTimeString(),
            $movement->permanenceEndedAt()->toDateTimeString(),
        );
        $this->assertSame('1 d', $movement->permanenceDuration());
    }

    public function test_deadline_situation_sem_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);

        $this->assertFalse($movement->hasDeadline());
        $this->assertSame(DeadlineSituation::SEM_PRAZO, $movement->deadlineSituation());
    }

    public function test_deadline_situation_no_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 10);

        $this->assertTrue($movement->hasDeadline());
        $this->assertSame(DeadlineSituation::NO_PRAZO, $movement->deadlineSituation());
    }

    public function test_deadline_situation_proximo_vencimento(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 3);

        $this->travel(1)->day();

        $this->assertSame(DeadlineSituation::PROXIMO_VENCIMENTO, $movement->fresh()->deadlineSituation());
    }

    public function test_deadline_situation_vencido(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 1);

        $this->travel(2)->days();

        $this->assertSame(DeadlineSituation::VENCIDO, $movement->fresh()->deadlineSituation());
    }

    public function test_deadline_situation_concluido_no_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 5);

        app(ProtocolService::class)->confirmReceipt($movement, $origin);

        $this->assertSame(DeadlineSituation::CONCLUIDO_NO_PRAZO, $movement->fresh()->deadlineSituation());
    }

    public function test_deadline_situation_concluido_apos_prazo(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 1);

        $this->travel(3)->days();

        app(ProtocolService::class)->confirmReceipt($movement, $origin);

        $this->assertSame(DeadlineSituation::CONCLUIDO_APOS_PRAZO, $movement->fresh()->deadlineSituation());
    }

    public function test_protocol_current_permanence(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward($protocol, $origin, DestinationType::SECTOR, $dest->id);
        app(ProtocolService::class)->confirmReceipt($movement, $origin);

        $this->travel(2)->days();

        $protocol->refresh();

        $this->assertNotNull($protocol->currentPermanenceStartedAt());
        $this->assertSame('2 d', $protocol->currentPermanenceDuration());
    }

    public function test_humanize_duration_formatting(): void
    {
        $this->assertNull(ProtocolMovement::humanizeDuration(null));
        $this->assertSame('menos de 1 min', ProtocolMovement::humanizeDuration(now(), now()));
        $this->assertSame('30 min', ProtocolMovement::humanizeDuration(now()->subMinutes(30)));
        $this->assertSame('3 h', ProtocolMovement::humanizeDuration(now()->subHours(3)));
        $this->assertSame('2 d', ProtocolMovement::humanizeDuration(now()->subDays(2)));
        $this->assertSame('2 d e 5 h', ProtocolMovement::humanizeDuration(now()->subDays(2)->subHours(5)));
    }

    public function test_providencia_completion_does_not_change_movement_deadline(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $dest = $this->makeSector($institution, 'Financeiro');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = $this->forwardWithDeadline($protocol, $origin, $dest->id, 5);

        $dueAt = $movement->due_at->toDateTimeString();

        ProtocolAction::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'protocol_movement_id' => $movement->id,
            'sector_id' => $dest->id,
            'user_id' => $origin->id,
            'action_type' => ProtocolActionType::CONFERENCIA_DOCUMENTAL,
            'description' => 'Providência concluída.',
            'status' => ProtocolActionStatus::CONCLUIDA,
            'occurred_at' => now(),
        ]);

        $movement->refresh();

        // Concluir a providência não altera o prazo da movimentação.
        $this->assertSame($dueAt, $movement->due_at->toDateTimeString());
        $this->assertSame(DeadlineSituation::NO_PRAZO, $movement->deadlineSituation());
    }

    public function test_permanence_is_measured_in_commission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $commission = Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => 'Comissão de Justiça',
            'acronym' => 'CJ',
            'is_active' => true,
        ]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $movement = app(ProtocolService::class)->forward(
            $protocol, $origin, DestinationType::COMMISSION, $commission->id
        );

        app(ProtocolService::class)->confirmReceipt($movement, $origin);
        $this->travel(2)->days();

        $movement->refresh();

        $this->assertTrue($movement->hasPermanence());
        $this->assertSame($commission->id, $movement->to_commission_id);
        $this->assertSame('2 d', $movement->permanenceDuration());

        // A responsabilidade atual passa a ser a comissão.
        $this->assertSame($commission->id, $protocol->fresh()->current_commission_id);
    }

    public function test_deadline_history_is_kept_after_new_movement(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $destA = $this->makeSector($institution, 'Financeiro');
        $destB = $this->makeSector($institution, 'Obras');
        $origin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $protocol = $this->createProtocol($institution, $origin, $sector);
        $first = $this->forwardWithDeadline($protocol, $origin, $destA->id, 5);
        $firstDueAt = $first->due_at->toDateTimeString();

        app(ProtocolService::class)->confirmReceipt($first, $origin);
        $this->travel(2)->days();

        $second = $this->forwardWithDeadline($protocol->fresh(), $origin, $destB->id, 10);

        $first->refresh();

        // O prazo da movimentação anterior permanece intacto após nova movimentação.
        $this->assertSame($firstDueAt, $first->due_at->toDateTimeString());
        $this->assertSame(5, $first->deadline);
        $this->assertNotNull($second->due_at);
        $this->assertNotSame($firstDueAt, $second->due_at->toDateTimeString());
    }

    private function forwardWithDeadline(Protocol $protocol, User $user, int $destinationId, int $days): ProtocolMovement
    {
        return app(ProtocolService::class)->forward(
            $protocol,
            $user,
            DestinationType::SECTOR,
            $destinationId,
            'Encaminhamento com prazo.',
            MovementType::ENCAMINHAMENTO,
            null,
            $days,
            DeadlineType::ADMINISTRATIVO,
            DeadlineMode::CORRIDOS,
        );
    }
}
