<?php

namespace App\Http\Controllers;

use App\Enums\DeadlineMode;
use App\Enums\DeadlineType;
use App\Enums\DestinationType;
use App\Enums\MovementType;
use App\Models\Commission;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Services\ProtocolService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Retificações de movimentações (FASE 2).
 *
 * RETIFICAÇÃO NÃO É EXCLUSÃO: o histórico original é preservado pelo
 * ProtocolService. Apenas campos revisáveis podem ser alterados; alterar o
 * destino exige autorização elevada, revalidada aqui no backend.
 */
class ProtocolMovementCorrectionController extends Controller
{
    public function __construct(private ProtocolService $service) {}

    /**
     * Registra uma retificação sobre uma movimentação existente.
     */
    public function store(Request $request, ProtocolMovement $movement)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', Rule::in(array_keys(MovementType::options()))],
            'deadline_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'deadline_type' => ['nullable', Rule::in(array_keys(DeadlineType::options()))],
            'deadline_mode' => ['nullable', Rule::in(array_keys(DeadlineMode::options()))],
            'deadline_notes' => ['nullable', 'string', 'max:2000'],
            'destination' => ['nullable', 'string', 'regex:/^(sector|commission):\d+$/'],
        ]);

        $user = $request->user();
        $changes = [];

        if ($request->filled('description')) {
            $changes['description'] = $data['description'];
        }

        if ($request->filled('type')) {
            $changes['type'] = MovementType::from($data['type']);
        }

        if ($request->filled('deadline_days')) {
            $mode = isset($data['deadline_mode'])
                ? DeadlineMode::from($data['deadline_mode'])
                : DeadlineMode::CORRIDOS;

            // A data-limite é recalculada a partir do envio original da movimentação.
            $changes['deadline'] = (int) $data['deadline_days'];
            $changes['due_at'] = $mode->addTo($movement->created_at ?? now(), (int) $data['deadline_days']);
            $changes['deadline_mode'] = $mode;

            if ($request->filled('deadline_type')) {
                $changes['deadline_type'] = DeadlineType::from($data['deadline_type']);
            }
        }

        if ($request->filled('deadline_notes')) {
            $changes['deadline_notes'] = $data['deadline_notes'];
        }

        $authorizedDestinationChange = false;

        if ($request->filled('destination')) {
            abort_unless(
                $user->hasPermission('protocols.movements.correct_destination'),
                403,
                'Acesso não autorizado para alterar o destino de uma movimentação.'
            );

            [$typeRaw, $idRaw] = explode(':', (string) $data['destination'], 2);

            $authorizedDestinationChange = true;

            $changes = array_merge($changes, $this->buildDestinationChange(
                DestinationType::from($typeRaw),
                (int) $idRaw,
            ));
        }

        $this->service->correctMovement($movement, $user, $data['reason'], $changes, $authorizedDestinationChange);

        return back()->with('success', 'Movimentação retificada com sucesso.');
    }

    /**
     * Constrói as alterações de destino mantendo o vínculo correto (setor XOR comissão).
     *
     * @return array<string,mixed>
     */
    private function buildDestinationChange(DestinationType $type, int $id): array
    {
        if ($type === DestinationType::COMMISSION) {
            abort_unless(Commission::whereKey($id)->exists(), 404);

            return [
                'to_commission_id' => $id,
                'to_sector_id' => null,
                'destination_type' => DestinationType::COMMISSION,
            ];
        }

        abort_unless(Sector::whereKey($id)->exists(), 404);

        return [
            'to_sector_id' => $id,
            'to_commission_id' => null,
            'destination_type' => DestinationType::SECTOR,
        ];
    }
}
