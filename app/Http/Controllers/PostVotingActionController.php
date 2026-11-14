<?php

namespace App\Http\Controllers;

use App\Enums\PostVotingStatus;
use App\Models\PostVotingAction;
use App\Models\Proposition;
use App\Models\Voting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FASE 4 — Encaminhamentos pós-votação.
 *
 * Uma proposição aprovada em Plenário não se transforma automaticamente em lei:
 * cada providência posterior é registrada formalmente e preservada no histórico.
 */
class PostVotingActionController extends Controller
{
    public function store(Request $request, Proposition $proposition)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(PostVotingStatus::options()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'performed_at' => ['nullable', 'date'],
        ], [], ['status' => 'situação pós-votação']);

        $status = PostVotingStatus::from($data['status']);

        $votingId = null;
        if ($request->filled('voting_id')) {
            $voting = Voting::whereKey($request->input('voting_id'))
                ->where('proposition_id', $proposition->id)
                ->first();

            if (! $voting) {
                throw ValidationException::withMessages([
                    'voting_id' => 'A votação informada não pertence a esta proposição.',
                ]);
            }

            $votingId = $voting->id;
        }

        PostVotingAction::create([
            'institution_id' => $proposition->institution_id,
            'proposition_id' => $proposition->id,
            'voting_id' => $votingId,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'performed_by' => $request->user()->id,
            'performed_at' => $data['performed_at'] ?? now(),
        ]);

        $newStatus = $status->propositionStatus();
        if ($newStatus) {
            $proposition->forceFill(['status' => $newStatus])->save();
        }

        AuditLogger::log('proposition.post_voting_registered', $proposition, [], [
            'status' => $status->value,
        ], 'Encaminhamento pós-votação registrado: '.$status->label().'.');

        return back()->with('success', 'Encaminhamento pós-votação registrado.');
    }

    public function destroy(Request $request, PostVotingAction $action)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('proposition.post_voting_removed', $action->proposition, [
            'status' => $action->status->value,
        ], [], 'Encaminhamento pós-votação removido. Justificativa: '.$data['justification']);

        $action->delete();

        return back()->with('success', 'Encaminhamento pós-votação removido.');
    }
}
