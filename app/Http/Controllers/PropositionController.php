<?php

namespace App\Http\Controllers;

use App\Enums\ForwardingStatus;
use App\Enums\OpinionResult;
use App\Enums\PostVotingStatus;
use App\Enums\PropositionModality;
use App\Enums\PropositionOrigin;
use App\Enums\PropositionStatus;
use App\Enums\VotingResult;
use App\Http\Requests\PropositionRequest;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Councilor;
use App\Models\Proposition;
use App\Models\PropositionAttachment;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\PropositionService;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Proposições legislativas: fluxo de rascunho, envio, análise e registro oficial.
 */
class PropositionController extends Controller
{
    public function __construct(
        private readonly PropositionService $service,
        private readonly AttachmentService $attachments,
    ) {}

    public function index(Request $request)
    {
        $query = Proposition::query()->with(['author', 'reviewer']);

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('ementa', 'like', "%{$term}%")
                    ->orWhere('official_number', 'like', "%{$term}%");
            });
        }

        if ($request->filled('modality')) {
            $query->where('modality', $request->input('modality'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->scopeForCouncilor($query);

        $propositions = $query->latest()->paginate(15)->withQueryString();

        return view('propositions.index', [
            'propositions' => $propositions,
            'modalities' => PropositionModality::options(),
            'statuses' => PropositionStatus::options(),
            'filters' => $request->only(['q', 'modality', 'status']),
        ]);
    }

    public function create()
    {
        return view('propositions.create', [
            'modalities' => PropositionModality::options(),
            'origins' => PropositionOrigin::options(),
            'councilors' => $this->activeCouncilors(),
        ]);
    }

    public function store(PropositionRequest $request)
    {
        $data = collect($request->validated())->except('attachments')->all();

        $proposition = $this->service->create($data, $request->user());

        foreach ($request->file('attachments', []) as $file) {
            $this->persistAttachment($proposition, $file, $request->user());
        }

        return redirect()->route('propositions.show', $proposition)
            ->with('success', 'Proposição criada como rascunho.');
    }

    public function show(Proposition $proposition)
    {
        $proposition->load([
            'author', 'authors', 'attachments.uploader', 'reviewer', 'registrar',
            'forwardings.commission', 'forwardings.forwardedBy', 'forwardings.receivedBy', 'forwardings.completedBy',
            'opinions.commission', 'opinions.rapporteur', 'opinions.registrar', 'opinions.attachment',
            'agendaItems.session',
            'votings.session', 'votings.registrar', 'votings.records.councilor',
            'postVotingActions.performer', 'postVotingActions.voting.session',
        ]);

        $timeline = AuditLog::query()
            ->with('user')
            ->where('auditable_type', Proposition::class)
            ->where('auditable_id', $proposition->id)
            ->latest()
            ->get();

        return view('propositions.show', [
            'proposition' => $proposition,
            'commissions' => Commission::where('is_active', true)->orderBy('display_order')->orderBy('name')->get(),
            'councilors' => $this->activeCouncilors(),
            'timeline' => $timeline,
            'forwardingStatuses' => ForwardingStatus::manualOptions(),
            'opinionResults' => OpinionResult::options(),
            'votingResults' => VotingResult::options(),
            'postVotingStatuses' => PostVotingStatus::options(),
        ]);
    }

    public function edit(Proposition $proposition)
    {
        if (! $this->isEditable($proposition)) {
            return redirect()->route('propositions.show', $proposition)
                ->with('error', 'Somente proposições em rascunho ou devolvidas podem ser editadas.');
        }

        $proposition->load('authors');

        return view('propositions.edit', [
            'proposition' => $proposition,
            'modalities' => PropositionModality::options(),
            'origins' => PropositionOrigin::options(),
            'councilors' => $this->activeCouncilors(),
        ]);
    }

    public function update(PropositionRequest $request, Proposition $proposition)
    {
        if (! $this->isEditable($proposition)) {
            return redirect()->route('propositions.show', $proposition)
                ->with('error', 'Somente proposições em rascunho ou devolvidas podem ser editadas.');
        }

        $data = collect($request->validated())->except('attachments')->all();

        $this->service->update($proposition, $data);

        foreach ($request->file('attachments', []) as $file) {
            $this->persistAttachment($proposition, $file, $request->user());
        }

        return redirect()->route('propositions.show', $proposition)
            ->with('success', 'Proposição atualizada.');
    }

    public function destroy(Proposition $proposition)
    {
        if ($proposition->status !== PropositionStatus::RASCUNHO) {
            return redirect()->route('propositions.index')
                ->with('error', 'Apenas proposições em rascunho podem ser excluídas.');
        }

        AuditLogger::log('proposition.deleted', $proposition, ['title' => $proposition->title], [], 'Proposição excluída.');

        $proposition->delete();

        return redirect()->route('propositions.index')
            ->with('success', 'Proposição excluída.');
    }

    public function submit(Proposition $proposition)
    {
        $this->service->submit($proposition, auth()->user());

        return back()->with('success', 'Proposição enviada para análise.');
    }

    public function review(Request $request, Proposition $proposition)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:aprovada,rejeitada,devolvida,em_analise'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->review(
            $proposition,
            $request->user(),
            PropositionStatus::from($data['decision']),
            $data['notes'] ?? null,
        );

        return back()->with('success', 'Análise registrada com sucesso.');
    }

    public function register(Proposition $proposition)
    {
        if ($proposition->status !== PropositionStatus::APROVADA) {
            return back()->with('error', 'Somente proposições aprovadas podem ser registradas.');
        }

        $proposition = $this->service->registerOfficial($proposition, auth()->user());

        return back()->with('success', 'Proposição registrada oficialmente: '.$proposition->official_number.'.');
    }

    public function storeAttachment(Request $request, Proposition $proposition)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $this->persistAttachment($proposition, $request->file('file'), $request->user());

        return back()->with('success', 'Anexo enviado com sucesso.');
    }

    public function downloadAttachment(PropositionAttachment $attachment)
    {
        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroyAttachment(PropositionAttachment $attachment)
    {
        AuditLogger::log('proposition.attachment_deleted', $attachment, [
            'original_name' => $attachment->original_name,
        ], [], 'Anexo da proposição removido.');

        $this->attachments->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }

    public function document(Proposition $proposition)
    {
        $proposition->load(['author', 'authors', 'institution']);

        $institution = Tenant::get() ?? $proposition->institution;

        $pdf = Pdf::loadView('propositions.pdf.document', [
            'proposition' => $proposition,
            'institution' => $institution,
        ]);

        return $pdf->stream('proposicao-'.$proposition->id.'.pdf');
    }

    /**
     * Restringe a listagem às proposições do vereador autenticado (autor ou coautor).
     */
    protected function scopeForCouncilor($query): void
    {
        $user = auth()->user();

        if ($user && $user->actsAsCouncilor() && $user->councilor_id) {
            $councilorId = $user->councilor_id;

            $query->where(function ($q) use ($councilorId) {
                $q->where('author_councilor_id', $councilorId)
                    ->orWhereHas('authors', fn ($a) => $a->where('councilors.id', $councilorId));
            });
        }
    }

    protected function persistAttachment(Proposition $proposition, UploadedFile $file, User $user): PropositionAttachment
    {
        $directory = config('protocolo.attachments_path')
            .'/'.$proposition->institution_id
            .'/proposicoes/'.$proposition->id;

        $meta = $this->attachments->store($file, $directory);

        return $proposition->attachments()->create([
            ...$meta,
            'institution_id' => $proposition->institution_id,
            'uploaded_by' => $user->id,
        ]);
    }

    protected function isEditable(Proposition $proposition): bool
    {
        return in_array($proposition->status, [PropositionStatus::RASCUNHO, PropositionStatus::DEVOLVIDA], true);
    }

    protected function activeCouncilors()
    {
        return Councilor::where('is_active', true)->orderBy('name')->get();
    }
}
