<?php

namespace App\Http\Controllers;

use App\Enums\VoteType;
use App\Enums\VotingResult;
use App\Enums\VotingType;
use App\Models\LegislativeSession;
use App\Models\QuorumSetting;
use App\Models\Voting;
use App\Models\VotingRecord;
use App\Services\AuditLogger;
use App\Services\VotingService;
use App\Support\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FASE 4 — Registro de votações (nominal, simbólica e secreta).
 *
 * A inclusão na pauta não aprova a matéria: apenas o resultado formal da
 * votação, validado pelo quórum configurado, altera a situação da proposição.
 */
class VotingController extends Controller
{
    public function __construct(
        private readonly VotingService $service,
    ) {}

    public function store(Request $request, LegislativeSession $session)
    {
        $data = $request->validate([
            'session_agenda_item_id' => ['required', 'integer'],
            'turno' => ['nullable', 'integer', 'min:1', 'max:9'],
            'type' => ['required', 'in:'.implode(',', array_keys(VotingType::options()))],
            'held_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['session_agenda_item_id' => 'item da pauta', 'type' => 'tipo de votação']);

        $item = $session->agendaItems()->whereKey($data['session_agenda_item_id'])->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'session_agenda_item_id' => 'O item selecionado não pertence a esta sessão.',
            ]);
        }

        $quorum = QuorumSetting::forInstitution($session->institution_id, $item->proposition?->modality);

        try {
            $voting = Voting::create([
                'institution_id' => $session->institution_id,
                'session_id' => $session->id,
                'session_agenda_item_id' => $item->id,
                'proposition_id' => $item->proposition_id,
                'turno' => $data['turno'] ?? 1,
                'type' => $data['type'],
                'quorum_rule' => $quorum?->rule,
                'quorum_min_presence' => $quorum?->min_presence,
                'held_at' => $data['held_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'session_agenda_item_id' => 'Já existe votação registrada para este item neste turno.',
            ]);
        }

        AuditLogger::log('voting.registered', $voting->proposition, [], [
            'session_id' => $session->id,
            'type' => $voting->type->value,
            'turno' => $voting->turno,
        ], 'Votação ('.$voting->type->label().') registrada na sessão nº '.$session->number.'.');

        return back()->with('success', 'Votação registrada.');
    }

    public function recordVote(Request $request, Voting $voting)
    {
        $data = $request->validate([
            'councilor_id' => ['required', Tenant::scopedExists('councilors')],
            'vote' => ['required', 'in:'.implode(',', array_keys(VoteType::options()))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['councilor_id' => 'vereador', 'vote' => 'voto']);

        VotingRecord::updateOrCreate(
            ['voting_id' => $voting->id, 'councilor_id' => $data['councilor_id']],
            [
                'institution_id' => $voting->institution_id,
                'vote' => $data['vote'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        $voting->recomputeCounts();

        AuditLogger::log('voting.vote_registered', $voting->proposition, [], [
            'voting_id' => $voting->id,
            'vote' => $data['vote'],
        ], 'Voto individual registrado na votação da sessão nº '.$voting->session->number.'.');

        return back()->with('success', 'Voto registrado.');
    }

    public function removeVote(Request $request, VotingRecord $record)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        $voting = $record->voting;
        $record->delete();
        $voting->recomputeCounts();

        AuditLogger::log('voting.vote_removed', $voting->proposition, [
            'voting_id' => $voting->id,
        ], [], 'Voto individual removido da votação da sessão nº '.$voting->session->number.'. Justificativa: '.$data['justification']);

        return back()->with('success', 'Voto removido.');
    }

    public function finalize(Request $request, Voting $voting)
    {
        $data = $request->validate([
            'result' => ['required', 'in:'.implode(',', array_keys(VotingResult::options()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['result' => 'resultado']);

        $result = VotingResult::from($data['result']);

        $this->service->validateResult($voting, $result);

        $voting->forceFill([
            'result' => $result,
            'result_registered_at' => now(),
            'registered_by' => $request->user()->id,
            'notes' => $data['notes'] ?? $voting->notes,
        ])->save();

        // Efeitos formais: situação na pauta e situação da proposição.
        $voting->agendaItem?->update(['status' => $result->agendaItemStatus()]);

        $proposition = $voting->proposition;
        $newStatus = $result->propositionStatus();

        if ($proposition && $newStatus) {
            $proposition->forceFill(['status' => $newStatus])->save();
        }

        AuditLogger::log('voting.result_registered', $proposition, [], [
            'voting_id' => $voting->id,
            'result' => $result->value,
        ], 'Resultado da votação registrado: '.$result->label().'.');

        return back()->with('success', 'Resultado da votação registrado.');
    }

    public function destroy(Request $request, Voting $voting)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('voting.deleted', $voting->proposition, [
            'voting_id' => $voting->id,
            'result' => $voting->result?->value,
        ], [], 'Votação removida. Justificativa: '.$data['justification']);

        $voting->delete();

        return back()->with('success', 'Votação removida.');
    }
}
