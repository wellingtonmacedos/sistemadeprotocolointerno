<?php

namespace App\Http\Controllers;

use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolActionType;
use App\Models\Protocol;
use App\Models\ProtocolAction;
use App\Models\ProtocolDivergence;
use App\Models\ProtocolMovement;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Providências: ações realizadas durante a tramitação de um protocolo.
 *
 * Registrar uma providência não cria movimentação nem transfere
 * responsabilidade. Se houver transferência, o usuário executa também o
 * encaminhamento pelo fluxo existente.
 */
class ProtocolActionController extends Controller
{
    /**
     * Registra uma nova providência vinculada ao protocolo.
     */
    public function store(Request $request, Protocol $protocol)
    {
        $data = $request->validate([
            'action_type' => ['required', Rule::in(array_keys(ProtocolActionType::options()))],
            'description' => ['required', 'string', 'max:5000'],
            'result' => ['nullable', 'string', 'max:5000'],
            'protocol_movement_id' => ['nullable', Tenant::scopedExists('protocol_movements')],
            'divergence_id' => ['nullable', Tenant::scopedExists('protocol_divergences')],
            'sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'commission_id' => ['nullable', Tenant::scopedExists('commissions')],
            'occurred_at' => ['nullable', 'date'],
            'requires_follow_up' => ['nullable', 'boolean'],
        ]);

        $sectorId = $data['sector_id'] ?? null;
        $commissionId = $data['commission_id'] ?? null;

        if ($sectorId !== null && $commissionId !== null) {
            throw ValidationException::withMessages([
                'commission_id' => 'Informe o local como setor ou comissão, não ambos.',
            ]);
        }

        // A providência pode estar vinculada a uma movimentação do próprio protocolo.
        $movementId = $data['protocol_movement_id'] ?? null;

        if ($movementId !== null) {
            $belongs = ProtocolMovement::query()
                ->whereKey($movementId)
                ->where('protocol_id', $protocol->id)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'protocol_movement_id' => 'A movimentação informada não pertence a este protocolo.',
                ]);
            }
        }

        // A providência pode tratar uma divergência do próprio protocolo.
        $divergenceId = $data['divergence_id'] ?? null;

        if ($divergenceId !== null) {
            $belongs = ProtocolDivergence::query()
                ->whereKey($divergenceId)
                ->where('protocol_id', $protocol->id)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'divergence_id' => 'A divergência informada não pertence a este protocolo.',
                ]);
            }
        }

        // Quando não informado, o local é a responsabilidade atual do protocolo.
        if ($sectorId === null && $commissionId === null) {
            $sectorId = $protocol->current_sector_id;
            $commissionId = $protocol->current_commission_id;
        }

        $action = ProtocolAction::create([
            'institution_id' => $protocol->institution_id,
            'protocol_id' => $protocol->id,
            'protocol_movement_id' => $movementId,
            'divergence_id' => $divergenceId,
            'sector_id' => $sectorId,
            'commission_id' => $commissionId,
            'user_id' => $request->user()->id,
            'action_type' => $data['action_type'],
            'description' => $data['description'],
            'result' => $data['result'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'requires_follow_up' => $request->boolean('requires_follow_up'),
            'status' => ProtocolActionStatus::REGISTRADA,
        ]);

        AuditLogger::log('protocol.action_created', $protocol, [], [
            'action_id' => $action->id,
            'action_type' => $action->action_type->value,
            'sector_id' => $sectorId,
            'commission_id' => $commissionId,
            'protocol_movement_id' => $movementId,
            'divergence_id' => $divergenceId,
        ], 'Providência registrada: '.$action->action_type->label().'.');

        return back()->with('success', 'Providência registrada com sucesso.');
    }
}
