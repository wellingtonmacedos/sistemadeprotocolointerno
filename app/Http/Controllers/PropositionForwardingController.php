<?php

namespace App\Http\Controllers;

use App\Enums\ForwardingStatus;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * FASE 2 — Encaminhamento de proposições para comissões e acompanhamento da situação.
 */
class PropositionForwardingController extends Controller
{
    public function store(Request $request, Proposition $proposition)
    {
        $data = $request->validate([
            'commission_id' => ['required', Tenant::scopedExists('commissions')],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['commission_id' => 'comissão']);

        $forwarding = $proposition->forwardings()->create([
            'institution_id' => $proposition->institution_id,
            'commission_id' => $data['commission_id'],
            'status' => ForwardingStatus::AGUARDANDO_RECEBIMENTO,
            'deadline' => $data['deadline'] ?? null,
            'notes' => $data['notes'] ?? null,
            'forwarded_by' => $request->user()->id,
            'forwarded_at' => now(),
        ]);

        AuditLogger::log('proposition.forwarded', $proposition, [], [
            'commission_id' => $forwarding->commission_id,
            'commission' => $forwarding->commission?->name,
        ], 'Encaminhada à comissão '.($forwarding->commission?->acronym ?? $forwarding->commission?->name).'.');

        return back()->with('success', 'Proposição encaminhada à comissão.');
    }

    public function receive(Request $request, PropositionForwarding $forwarding)
    {
        $this->authorizeCommissionAction($request, $forwarding->commission_id);

        if ($forwarding->received_at === null) {
            $forwarding->update([
                'status' => ForwardingStatus::EM_ANALISE,
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            AuditLogger::log('proposition.forwarding_received', $forwarding->proposition, [], [
                'commission' => $forwarding->commission?->name,
            ], 'Recebimento confirmado pela comissão.');
        }

        return back()->with('success', 'Recebimento registrado.');
    }

    public function updateStatus(Request $request, PropositionForwarding $forwarding)
    {
        $this->authorizeCommissionAction($request, $forwarding->commission_id);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(ForwardingStatus::manualOptions()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = ForwardingStatus::from($data['status']);

        $forwarding->update([
            'status' => $status,
            'notes' => $data['notes'] ?? $forwarding->notes,
            'completed_by' => $status === ForwardingStatus::CONCLUIDA ? $request->user()->id : $forwarding->completed_by,
            'completed_at' => $status === ForwardingStatus::CONCLUIDA ? now() : null,
        ]);

        AuditLogger::log('proposition.forwarding_updated', $forwarding->proposition, [], [
            'status' => $status->value,
        ], 'Situação do encaminhamento alterada para '.$status->label().'.');

        return back()->with('success', 'Situação do encaminhamento atualizada.');
    }

    public function destroy(Request $request, PropositionForwarding $forwarding)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('proposition.forwarding_cancelled', $forwarding->proposition, [
            'commission' => $forwarding->commission?->name,
            'status' => $forwarding->status->value,
        ], [], 'Encaminhamento cancelado. Justificativa: '.$data['justification']);

        $forwarding->delete();

        return back()->with('success', 'Encaminhamento cancelado.');
    }

    /**
     * Recebimento e movimentação da situação: permitido a quem possui a
     * permissão global de encaminhar OU a qualquer membro ativo da comissão
     * de destino. Criar/cancelar encaminhamento continua restrito à permissão.
     */
    private function authorizeCommissionAction(Request $request, ?int $commissionId): void
    {
        $user = $request->user();

        $allowed = $user !== null && (
            $user->hasPermission('propositions.forward')
            || ($commissionId !== null && $user->isActiveCommissionMember((int) $commissionId))
        );

        abort_unless($allowed, 403, 'Apenas membros da comissão ou perfis autorizados podem movimentar este encaminhamento.');
    }
}
