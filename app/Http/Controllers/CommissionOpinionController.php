<?php

namespace App\Http\Controllers;

use App\Enums\OpinionResult;
use App\Models\CommissionOpinion;
use App\Models\Proposition;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * FASE 2 — Registro de pareceres de comissão sobre proposições.
 */
class CommissionOpinionController extends Controller
{
    public function store(Request $request, Proposition $proposition)
    {
        $this->authorizeOpinion($request, (int) $request->input('commission_id'));

        $data = $request->validate([
            'commission_id' => ['required', Tenant::scopedExists('commissions')],
            'forwarding_id' => ['nullable', Tenant::scopedExists('proposition_forwardings')],
            'rapporteur_councilor_id' => ['nullable', Tenant::scopedExists('councilors')],
            'result' => ['required', 'in:'.implode(',', array_keys(OpinionResult::options()))],
            'text' => ['required', 'string', 'max:20000'],
            'opinion_date' => ['nullable', 'date'],
            'proposition_attachment_id' => ['nullable', Tenant::scopedExists('proposition_attachments')],
        ], [], [
            'commission_id' => 'comissão',
            'rapporteur_councilor_id' => 'relator',
            'text' => 'texto do parecer',
        ]);

        $result = OpinionResult::from($data['result']);

        $opinion = $proposition->opinions()->create([
            ...$data,
            'institution_id' => $proposition->institution_id,
            'opinion_date' => $data['opinion_date'] ?? now()->toDateString(),
            'registered_by' => $request->user()->id,
        ]);

        // O parecer atualiza a situação do encaminhamento correspondente.
        if ($opinion->forwarding_id) {
            $opinion->forwarding?->update([
                'status' => $result->forwardingStatus(),
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        }

        AuditLogger::log('proposition.opinion_registered', $proposition, [], [
            'commission_id' => $opinion->commission_id,
            'result' => $result->value,
        ], 'Parecer registrado ('.$result->label().') pela comissão '.($opinion->commission?->acronym ?? $opinion->commission?->name).'.');

        return back()->with('success', 'Parecer registrado.');
    }

    public function destroy(Request $request, CommissionOpinion $opinion)
    {
        $this->authorizeOpinion($request, (int) $opinion->commission_id);

        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('proposition.opinion_deleted', $opinion->proposition, [
            'commission' => $opinion->commission?->name,
            'result' => $opinion->result->value,
        ], [], 'Parecer removido. Justificativa: '.$data['justification']);

        $opinion->delete();

        return back()->with('success', 'Parecer removido.');
    }

    /**
     * Registro/remoção de parecer: permitido a quem possui a permissão global
     * OU a quem atua na comissão como relator ou presidente (membro ativo).
     */
    private function authorizeOpinion(Request $request, int $commissionId): void
    {
        $user = $request->user();

        $allowed = $user !== null && (
            $user->hasPermission('propositions.opinion')
            || ($commissionId > 0 && $user->hasCommissionRole($commissionId, ['relator', 'presidente']))
        );

        abort_unless($allowed, 403, 'Apenas o relator, o presidente ou perfis autorizados podem registrar pareceres.');
    }
}
