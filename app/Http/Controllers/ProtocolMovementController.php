<?php

namespace App\Http\Controllers;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\DivergenceType;
use App\Enums\MovementType;
use App\Enums\RefusalReason;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Services\ProtocolService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tramitação: encaminhamento, recebimento e andamentos dos protocolos.
 *
 * Setores e comissões são destinos institucionais independentes. A autorização
 * é sempre revalidada no backend, independentemente da interface.
 */
class ProtocolMovementController extends Controller
{
    public function __construct(private ProtocolService $service) {}

    /**
     * Encaminha o protocolo a um setor ou comissão.
     */
    public function forward(Request $request, Protocol $protocol)
    {
        $data = $request->validate([
            'destination_type' => ['nullable', Rule::in(array_keys(DestinationType::options()))],
            'to_sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'to_commission_id' => ['nullable', Tenant::scopedExists('commissions')],
            'description' => ['nullable', 'string', 'max:2000'],
            'deadline_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'deadline_type' => ['nullable', 'required_with:deadline_days', Rule::in(array_keys(DeadlineType::options()))],
            'deadline_mode' => ['nullable', Rule::in(array_keys(DeadlineMode::options()))],
            'deadline_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $sectorId = $data['to_sector_id'] ?? null;
        $commissionId = $data['to_commission_id'] ?? null;

        // Não permitir dois destinos simultâneos nem ausência de destino.
        if ($sectorId !== null && $commissionId !== null) {
            throw ValidationException::withMessages([
                'to_commission_id' => 'Selecione apenas um destino: setor ou comissão.',
            ]);
        }

        $destinationType = isset($data['destination_type'])
            ? DestinationType::from($data['destination_type'])
            : ($commissionId !== null ? DestinationType::COMMISSION : DestinationType::SECTOR);

        if ($destinationType === DestinationType::COMMISSION) {
            abort_unless(
                $request->user()->hasPermission('protocols.dispatch_commission'),
                403,
                'Acesso não autorizado para encaminhar a comissões.'
            );

            if ($commissionId === null) {
                throw ValidationException::withMessages(['to_commission_id' => 'Selecione a comissão de destino.']);
            }

            $destinationId = (int) $commissionId;
        } else {
            if ($sectorId === null) {
                throw ValidationException::withMessages(['to_sector_id' => 'Selecione o setor de destino.']);
            }

            $destinationId = (int) $sectorId;
        }

        $this->service->forward(
            $protocol,
            $request->user(),
            $destinationType,
            $destinationId,
            $data['description'] ?? null,
            MovementType::ENCAMINHAMENTO,
            null,
            isset($data['deadline_days']) ? (int) $data['deadline_days'] : null,
            $data['deadline_type'] ?? null,
            $data['deadline_mode'] ?? null,
            $data['deadline_notes'] ?? null,
        );

        return back()->with('success', 'Protocolo encaminhado com sucesso.');
    }

    /**
     * Confirma o recebimento do protocolo pelo destino (setor ou comissão).
     */
    public function receive(Request $request, ProtocolMovement $movement)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($movement->to_commission_id) {
            abort_unless(
                $user->hasPermission('protocols.receive_commission'),
                403,
                'Acesso não autorizado para receber em nome de comissões.'
            );
        } else {
            abort_unless($user->hasPermission('protocols.receive'), 403);

            // Usuário vinculado a um setor só recebe no próprio setor.
            abort_if(
                $user->sector_id !== null && $movement->to_sector_id !== $user->sector_id,
                403,
                'Você só pode receber documentos no seu setor.'
            );
        }

        $this->service->confirmReceipt($movement, $user, $data['description'] ?? null);

        return back()->with('success', 'Recebimento confirmado com sucesso.');
    }

    /**
     * Recusa formalmente o recebimento do protocolo pelo destino (setor ou comissão).
     *
     * A recusa exige motivo e descrição da ocorrência; a responsabilidade atual
     * permanece com o remetente e a movimentação original é preservada.
     */
    public function refuse(Request $request, ProtocolMovement $movement)
    {
        $data = $request->validate([
            'refusal_reason' => ['required', Rule::in(array_keys(RefusalReason::options()))],
            'refusal_notes' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($movement->to_commission_id) {
            abort_unless(
                $user->hasPermission('protocols.receive_commission'),
                403,
                'Acesso não autorizado para recusar em nome de comissões.'
            );
        } else {
            abort_unless($user->hasPermission('protocols.receive'), 403);

            // Usuário vinculado a um setor só recusa no próprio setor.
            abort_if(
                $user->sector_id !== null && $movement->to_sector_id !== $user->sector_id,
                403,
                'Você só pode recusar documentos no seu setor.'
            );
        }

        // Somente movimentações ainda pendentes podem ser recusadas.
        abort_unless(
            $movement->isPending(),
            403,
            'Somente movimentações aguardando recebimento podem ser recusadas.'
        );

        $this->service->refuseReceipt(
            $movement,
            $user,
            RefusalReason::from($data['refusal_reason']),
            $data['refusal_notes'],
        );

        return back()->with('success', 'Recebimento recusado com sucesso.');
    }

    /**
     * Confirma o recebimento registrando, na MESMA transação, uma divergência.
     *
     * Uma divergência não impede o recebimento: o documento é recebido e a
     * ocorrência é registrada para acompanhamento posterior.
     */
    public function receiveWithDivergence(Request $request, ProtocolMovement $movement)
    {
        $data = $request->validate([
            'divergence_type' => ['required', Rule::in(array_keys(DivergenceType::options()))],
            'divergence_description' => ['required', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($movement->to_commission_id) {
            abort_unless(
                $user->hasPermission('protocols.receive_commission'),
                403,
                'Acesso não autorizado para receber em nome de comissões.'
            );
        } else {
            abort_unless($user->hasPermission('protocols.receive'), 403);

            abort_if(
                $user->sector_id !== null && $movement->to_sector_id !== $user->sector_id,
                403,
                'Você só pode receber documentos no seu setor.'
            );
        }

        abort_unless(
            $movement->isPending(),
            403,
            'Somente movimentações aguardando recebimento podem ser recebidas.'
        );

        $this->service->confirmReceiptWithDivergence(
            $movement,
            $user,
            DivergenceType::from($data['divergence_type']),
            $data['divergence_description'],
            $data['description'] ?? null,
        );

        return back()->with('success', 'Recebimento confirmado com registro de divergência.');
    }

    /**
     * Registra um andamento (despacho, parecer, devolução, conclusão...).
     */
    public function register(Request $request, Protocol $protocol)
    {
        $types = [
            MovementType::DESPACHO->value,
            MovementType::PARECER->value,
            MovementType::DEVOLUCAO->value,
            MovementType::SOLICITACAO_COMPLEMENTACAO->value,
            MovementType::CONCLUSAO->value,
            MovementType::ARQUIVAMENTO->value,
        ];

        $data = $request->validate([
            'type' => ['required', Rule::in($types)],
            'description' => ['required', 'string', 'max:5000'],
            'to_sector_id' => ['nullable', 'required_if:type,'.MovementType::DEVOLUCAO->value, Tenant::scopedExists('sectors')],
        ]);

        $extra = [];

        if (! empty($data['to_sector_id'])) {
            $extra['to_sector_id'] = (int) $data['to_sector_id'];
        }

        $this->service->register(
            $protocol,
            $request->user(),
            MovementType::from($data['type']),
            $data['description'],
            $extra,
        );

        return back()->with('success', 'Andamento registrado com sucesso.');
    }
}
