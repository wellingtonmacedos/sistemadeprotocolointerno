<?php

namespace Tests\Feature;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DivergenceType;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Enums\ProtocolPriority;
use App\Enums\RefusalReason;
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
 * FASE 2 — Pacote F: caixa de entrada operacional (§8).
 *
 * A caixa de entrada passa a organizar as movimentações em categorias de
 * acompanhamento (aguardando recebimento, recebidos, recusados, divergência
 * aberta, providência pendente, próximos do vencimento e prazo vencido),
 * preservando o isolamento institucional e as pendências legislativas.
 */
class ProtocolInboxTest extends TestCase
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

    /**
     * Cria um protocolo e o encaminha a um setor de destino, retornando
     * [protocolo, movimentação, autor].
     *
     * @return array{0:Protocol,1:ProtocolMovement,2:User}
     */
    private function makeFlow(Institution $institution, Sector $from, Sector $to, ?int $deadlineDays = null, string $subject = 'Protocolo Caixa de Entrada'): array
    {
        $author = $this->makeUser($institution, 'administrador', ['sector_id' => $from->id]);

        $protocol = app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => $subject,
            'sender_name' => 'Interessado Teste',
            'priority' => ProtocolPriority::NORMAL->value,
        ], $author);

        $movement = app(ProtocolService::class)->forward(
            $protocol,
            $author,
            DestinationType::SECTOR,
            $to->id,
            'Encaminhamento ao destino.',
            MovementType::ENCAMINHAMENTO,
            null,
            $deadlineDays,
            $deadlineDays ? DeadlineType::ADMINISTRATIVO : null,
            $deadlineDays ? DeadlineMode::CORRIDOS : null,
        );

        return [$protocol, $movement, $author];
    }

    public function test_inbox_default_category_lists_movements_awaiting_receipt(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$protocol] = $this->makeFlow($institution, $from, $to);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertSee('Aguardando recebimento')
            ->assertViewHas('activeFilter', 'aguardando');
    }

    public function test_inbox_received_category_lists_received_movements_only(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$received, $movementA] = $this->makeFlow($institution, $from, $to);
        [$pending] = $this->makeFlow($institution, $from, $to);

        app(ProtocolService::class)->confirmReceipt($movementA, $receiver);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'recebidos']))
            ->assertOk()
            ->assertSee($received->formatted_number)
            ->assertDontSee($pending->formatted_number)
            ->assertViewHas('activeFilter', 'recebidos');
    }

    public function test_inbox_refused_category_shows_refusal_reason(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$protocol, $movement] = $this->makeFlow($institution, $from, $to);

        app(ProtocolService::class)->refuseReceipt(
            $movement,
            $receiver,
            RefusalReason::DESTINO_INCORRETO,
            'Encaminhado ao setor errado.',
        );

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'recusados']))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertSee('Destino incorreto')
            ->assertViewHas('activeFilter', 'recusados');
    }

    public function test_inbox_divergence_category_lists_open_divergences(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$protocol, $movement] = $this->makeFlow($institution, $from, $to);

        app(ProtocolService::class)->confirmReceiptWithDivergence(
            $movement,
            $receiver,
            DivergenceType::ANEXO_FALTANTE,
            'Recebido sem o anexo III.',
        );

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'divergencias']))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertSee('Divergência')
            ->assertViewHas('activeFilter', 'divergencias');
    }

    public function test_inbox_providencia_category_lists_pending_actions(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$protocol, $movement, $author] = $this->makeFlow($institution, $from, $to);

        ProtocolAction::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'protocol_id' => $protocol->id,
            'protocol_movement_id' => $movement->id,
            'sector_id' => $to->id,
            'user_id' => $author->id,
            'action_type' => ProtocolActionType::CONFERENCIA_DOCUMENTAL,
            'description' => 'Conferência pendente.',
            'status' => ProtocolActionStatus::REGISTRADA,
            'occurred_at' => now(),
        ]);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'providencias']))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertViewHas('activeFilter', 'providencias');
    }

    public function test_inbox_near_due_category_lists_movements_due_soon(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$nearDue] = $this->makeFlow($institution, $from, $to, 1);
        [$farDue] = $this->makeFlow($institution, $from, $to, 30);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'proximos']))
            ->assertOk()
            ->assertSee($nearDue->formatted_number)
            ->assertDontSee($farDue->formatted_number)
            ->assertViewHas('activeFilter', 'proximos');
    }

    public function test_inbox_overdue_category_lists_late_movements(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$overdue] = $this->makeFlow($institution, $from, $to, 1);

        $this->travel(3)->days();

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'vencidos']))
            ->assertOk()
            ->assertSee($overdue->formatted_number)
            ->assertViewHas('activeFilter', 'vencidos');
    }

    public function test_inbox_counts_reflect_each_category(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        $this->makeFlow($institution, $from, $to);
        [, $movementB] = $this->makeFlow($institution, $from, $to);
        [, $movementC] = $this->makeFlow($institution, $from, $to);

        app(ProtocolService::class)->confirmReceipt($movementB, $receiver);
        app(ProtocolService::class)->refuseReceipt($movementC, $receiver, RefusalReason::OUTRO, 'Recusado.');

        $this->actingAs($receiver)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertViewHas('inboxCounts', function (array $counts) {
                return $counts['aguardando'] === 1
                    && $counts['recebidos'] === 1
                    && $counts['recusados'] === 1
                    && $counts['divergencias'] === 0
                    && $counts['vencidos'] === 0;
            });
    }

    public function test_inbox_does_not_leak_other_institution(): void
    {
        $alfa = $this->makeInstitution(['name' => 'Câmara Alfa']);
        $beta = $this->makeInstitution(['name' => 'Câmara Beta']);

        $fromA = $this->makeSector($alfa, 'Protocolo');
        $toA = $this->makeSector($alfa, 'Financeiro');
        $fromB = $this->makeSector($beta, 'Protocolo');
        $toB = $this->makeSector($beta, 'Financeiro');

        $receiverA = $this->makeUser($alfa, 'servidor_setor', ['sector_id' => $toA->id]);

        [$protocolA] = $this->makeFlow($alfa, $fromA, $toA, null, 'Documento exclusivo da Câmara Alfa');
        $this->makeFlow($beta, $fromB, $toB, null, 'Documento exclusivo da Câmara Beta');

        $this->actingAs($receiverA)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertSee($protocolA->formatted_number)
            ->assertSee('Documento exclusivo da Câmara Alfa')
            ->assertDontSee('Documento exclusivo da Câmara Beta');
    }

    public function test_inbox_requires_receive_permission(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $viewer = $this->makeUser($institution, 'vereador', ['sector_id' => $sector->id]);

        $this->actingAs($viewer)
            ->get(route('protocols.inbox'))
            ->assertForbidden();
    }

    public function test_inbox_invalid_filter_defaults_to_awaiting(): void
    {
        $institution = $this->makeInstitution();
        $from = $this->makeSector($institution, 'Protocolo');
        $to = $this->makeSector($institution, 'Financeiro');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $to->id]);

        [$protocol] = $this->makeFlow($institution, $from, $to);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'inexistente']))
            ->assertOk()
            ->assertSee($protocol->formatted_number)
            ->assertViewHas('activeFilter', 'aguardando');
    }

    public function test_inbox_keeps_legislative_pending_section(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $admin = $this->makeUser($institution, 'administrador', ['sector_id' => $sector->id]);

        $this->actingAs($admin)
            ->get(route('protocols.inbox'))
            ->assertOk()
            ->assertSee('Pendências legislativas');
    }

    public function test_inbox_empty_category_shows_empty_state(): void
    {
        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $receiver = $this->makeUser($institution, 'servidor_setor', ['sector_id' => $sector->id]);

        $this->actingAs($receiver)
            ->get(route('protocols.inbox', ['filtro' => 'vencidos']))
            ->assertOk()
            ->assertSee('Nenhum registro nesta categoria.');
    }
}
