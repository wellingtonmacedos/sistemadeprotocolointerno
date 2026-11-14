<?php

namespace App\Http\Controllers;

use App\Enums\AgendaItemStatus;
use App\Enums\AppreciationType;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Enums\VoteType;
use App\Enums\VotingResult;
use App\Enums\VotingType;
use App\Models\Councilor;
use App\Models\LegislativeSession;
use App\Models\Proposition;
use App\Models\SessionDocument;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FASE 3 — Sessões legislativas e documentos da sessão.
 */
class LegislativeSessionController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    public function index(Request $request)
    {
        $query = LegislativeSession::query()->with('president')->withCount('agendaItems');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('number', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }

        return view('sessions.index', [
            'sessions' => $query->orderByDesc('date')->orderByDesc('number')->paginate(15)->withQueryString(),
            'types' => SessionType::options(),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function create()
    {
        return view('sessions.form', [
            'session' => new LegislativeSession(['type' => SessionType::ORDINARIA, 'status' => SessionStatus::AGENDADA]),
            'councilors' => $this->councilors(),
            'types' => SessionType::options(),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $session = LegislativeSession::create([
            ...$data,
            'institution_id' => Tenant::id() ?? $request->user()->institution_id,
        ]);

        AuditLogger::log('session.created', $session, [], [
            'number' => $session->number,
            'type' => $session->type->value,
            'date' => $session->date->toDateString(),
        ], 'Sessão '.$session->type->label().' nº '.$session->number.' cadastrada.');

        return redirect()->route('sessions.show', $session)->with('success', 'Sessão cadastrada.');
    }

    public function show(LegislativeSession $session)
    {
        $session->load([
            'president',
            'agendaItems.proposition.author',
            'agendaItems.documents',
            'documents.uploader',
            'votings.proposition',
            'votings.registrar',
            'votings.records.councilor',
        ]);

        $usedPropositions = $session->agendaItems->pluck('proposition_id');

        return view('sessions.show', [
            'session' => $session,
            'councilors' => $this->councilors(),
            'propositions' => Proposition::query()
                ->whereNotIn('id', $usedPropositions)
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'appreciationTypes' => AppreciationType::options(),
            'agendaStatuses' => AgendaItemStatus::options(),
            'statuses' => SessionStatus::options(),
            'votingTypes' => VotingType::options(),
            'voteTypes' => VoteType::options(),
            'votingResults' => VotingResult::options(),
        ]);
    }

    public function edit(LegislativeSession $session)
    {
        return view('sessions.form', [
            'session' => $session,
            'councilors' => $this->councilors(),
            'types' => SessionType::options(),
            'statuses' => SessionStatus::options(),
        ]);
    }

    public function update(Request $request, LegislativeSession $session)
    {
        $data = $this->validated($request, $session);

        $session->update($data);

        AuditLogger::log('session.updated', $session, [], $data, 'Sessão nº '.$session->number.' atualizada.');

        return redirect()->route('sessions.show', $session)->with('success', 'Sessão atualizada.');
    }

    public function destroy(Request $request, LegislativeSession $session)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('session.deleted', $session, [
            'number' => $session->number,
        ], [], 'Sessão excluída. Justificativa: '.$data['justification']);

        $session->delete();

        return redirect()->route('sessions.index')->with('success', 'Sessão excluída.');
    }

    public function storeDocument(Request $request, LegislativeSession $session)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $directory = config('protocolo.attachments_path').'/'.$session->institution_id.'/sessoes/'.$session->id;
        $meta = $this->attachments->store($request->file('file'), $directory);

        $document = $session->documents()->create([
            ...$meta,
            'institution_id' => $session->institution_id,
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('session.document_added', $session, [], [
            'original_name' => $document->original_name,
        ], 'Documento anexado à sessão.');

        return back()->with('success', 'Documento anexado.');
    }

    public function downloadDocument(SessionDocument $document)
    {
        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, SessionDocument $document)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['justification' => 'justificativa']);

        AuditLogger::log('session.document_deleted', $document->session, [
            'original_name' => $document->original_name,
        ], [], 'Documento da sessão removido. Justificativa: '.$data['justification']);

        $this->attachments->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Documento removido.');
    }

    protected function validated(Request $request, ?LegislativeSession $session = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999999'],
            'type' => ['required', 'in:'.implode(',', array_keys(SessionType::options()))],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'president_councilor_id' => ['nullable', Tenant::scopedExists('councilors')],
            'status' => ['required', 'in:'.implode(',', array_keys(SessionStatus::options()))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'president_councilor_id' => 'presidente da sessão',
            'number' => 'número da sessão',
        ]);

        $year = (int) date('Y', strtotime($data['date']));

        $duplicate = LegislativeSession::query()
            ->where('number', $data['number'])
            ->whereYear('date', $year)
            ->when($session, fn ($q) => $q->whereKeyNot($session->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'number' => "Já existe uma sessão nº {$data['number']} no ano {$year}.",
            ]);
        }

        return $data;
    }

    protected function councilors()
    {
        return Councilor::where('is_active', true)->orderBy('name')->get();
    }
}
