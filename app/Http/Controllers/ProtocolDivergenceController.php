<?php

namespace App\Http\Controllers;

use App\Enums\DivergenceStatus;
use App\Enums\DivergenceType;
use App\Models\Protocol;
use App\Models\ProtocolDivergence;
use App\Models\ProtocolMovement;
use App\Services\ProtocolService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Divergências: ocorrências registradas sobre um protocolo, durante a
 * permanência no setor, no recebimento ou após o recebimento.
 *
 * Registrar ou atualizar uma divergência NÃO cria movimentação, NÃO marca
 * recebimento e NÃO altera a responsabilidade atual do protocolo.
 */
class ProtocolDivergenceController extends Controller
{
    public function __construct(private ProtocolService $service) {}

    /**
     * Registra uma nova divergência vinculada ao protocolo.
     */
    public function store(Request $request, Protocol $protocol)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DivergenceType::options()))],
            'description' => ['required', 'string', 'max:5000'],
            'protocol_movement_id' => ['nullable', Tenant::scopedExists('protocol_movements')],
            'sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'commission_id' => ['nullable', Tenant::scopedExists('commissions')],
        ]);

        $sectorId = $data['sector_id'] ?? null;
        $commissionId = $data['commission_id'] ?? null;

        if ($sectorId !== null && $commissionId !== null) {
            throw ValidationException::withMessages([
                'commission_id' => 'Informe o local como setor ou comissão, não ambos.',
            ]);
        }

        // A divergência pode estar vinculada a uma movimentação do próprio protocolo.
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

        $this->service->recordDivergence(
            $protocol,
            $request->user(),
            DivergenceType::from($data['type']),
            $data['description'],
            [
                'protocol_movement_id' => $movementId,
                'sector_id' => $sectorId,
                'commission_id' => $commissionId,
            ],
        );

        return back()->with('success', 'Divergência registrada com sucesso.');
    }

    /**
     * Atualiza a situação de uma divergência. O encerramento sem resolução
     * exige justificativa.
     */
    public function update(Request $request, ProtocolDivergence $divergence)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DivergenceStatus::options()))],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->service->updateDivergenceStatus(
            $divergence,
            DivergenceStatus::from($data['status']),
            $data['resolution_notes'] ?? null,
        );

        return back()->with('success', 'Divergência atualizada com sucesso.');
    }
}
