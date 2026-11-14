<?php

namespace App\Http\Controllers;

use App\Enums\AgendaItemStatus;
use App\Enums\AppreciationType;
use App\Models\LegislativeSession;
use App\Models\SessionAgendaItem;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FASE 3 — Pauta e ordem do dia.
 *
 * A inclusão, alteração, retirada ou adiamento de um item na pauta NÃO altera
 * automaticamente a situação da proposição (aprovação é ato do Plenário).
 */
class SessionAgendaItemController extends Controller
{
    public function store(Request $request, LegislativeSession $session)
    {
        $data = $request->validate([
            'proposition_id' => ['required', Tenant::scopedExists('propositions')],
            'appreciation_type' => ['required', 'in:'.implode(',', array_keys(AppreciationType::options()))],
            'will_discuss' => ['nullable', 'boolean'],
            'will_vote' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['proposition_id' => 'proposição']);

        $exists = $session->agendaItems()->where('proposition_id', $data['proposition_id'])->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'proposition_id' => 'Esta proposição já consta na pauta desta sessão.',
            ]);
        }

        $item = $session->agendaItems()->create([
            'institution_id' => $session->institution_id,
            'proposition_id' => $data['proposition_id'],
            'position' => $session->nextPosition(),
            'appreciation_type' => $data['appreciation_type'],
            'will_discuss' => $request->boolean('will_discuss', true),
            'will_vote' => $request->boolean('will_vote', true),
            'status' => AgendaItemStatus::PAUTADA,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLogger::log('proposition.scheduled', $item->proposition, [], [
            'session_id' => $session->id,
            'position' => $item->position,
        ], 'Incluída na pauta da sessão nº '.$session->number.' (ordem '.$item->position.').');

        return back()->with('success', 'Matéria incluída na pauta.');
    }

    public function updateStatus(Request $request, SessionAgendaItem $item)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(AgendaItemStatus::options()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = AgendaItemStatus::from($data['status']);

        $item->update([
            'status' => $status,
            'notes' => $data['notes'] ?? $item->notes,
        ]);

        AuditLogger::log('proposition.agenda_status_changed', $item->proposition, [], [
            'session_id' => $item->session_id,
            'status' => $status->value,
        ], 'Situação na pauta da sessão nº '.$item->session->number.' alterada para '.$status->label().'.');

        return back()->with('success', 'Situação do item atualizada.');
    }

    public function move(Request $request, SessionAgendaItem $item)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        // reorder() remove a ordenação herdada da relação (orderBy 'position'),
        // garantindo que a busca pelo vizinho respeite a direção solicitada.
        $query = $item->session->agendaItems()->reorder();

        $neighbor = $data['direction'] === 'up'
            ? $query->where('position', '<', $item->position)->orderByDesc('position')->first()
            : $query->where('position', '>', $item->position)->orderBy('position')->first();

        if (! $neighbor) {
            return back()->with('error', 'O item já está no limite da pauta.');
        }

        $currentPosition = $item->position;
        $item->update(['position' => $neighbor->position]);
        $neighbor->update(['position' => $currentPosition]);

        AuditLogger::log('proposition.agenda_reordered', $item->proposition, [
            'position' => $currentPosition,
        ], [
            'position' => $neighbor->position,
        ], 'Ordem na pauta da sessão nº '.$item->session->number.' alterada.');

        return back()->with('success', 'Ordem da pauta atualizada.');
    }

    public function destroy(Request $request, SessionAgendaItem $item)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        $session = $item->session;
        $proposition = $item->proposition;
        $position = $item->position;

        AuditLogger::log('proposition.removed_from_agenda', $proposition, [
            'session_id' => $session->id,
            'position' => $position,
        ], [], 'Retirada da pauta da sessão nº '.$session->number.'. Justificativa: '.$data['justification']);

        $item->delete();

        // Reordena os itens restantes para manter a sequência.
        $session->agendaItems()->get()->each(function (SessionAgendaItem $remaining, int $index) {
            if ($remaining->position !== $index + 1) {
                $remaining->update(['position' => $index + 1]);
            }
        });

        return back()->with('success', 'Matéria retirada da pauta.');
    }
}
