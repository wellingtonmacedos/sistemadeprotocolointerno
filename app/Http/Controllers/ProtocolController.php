<?php

namespace App\Http\Controllers;

use App\Enums\DivergenceStatus;
use App\Enums\DocumentType;
use App\Enums\ForwardingStatus;
use App\Enums\MovementStatus;
use App\Enums\PropositionStatus;
use App\Enums\ProtocolActionStatus;
use App\Enums\ProtocolPriority;
use App\Enums\ProtocolStatus;
use App\Http\Requests\ProtocolRequest;
use App\Models\Commission;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\ProtocolService;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Protocolos: cadastro, consulta, tramitação e documentos.
 */
class ProtocolController extends Controller
{
    public function __construct(
        private ProtocolService $service,
        private AttachmentService $attachments,
    ) {}

    /**
     * Listagem paginada com filtros.
     */
    public function index(Request $request)
    {
        $protocols = Protocol::query()
            ->with(['destinationSector', 'currentSector'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($sub) use ($term) {
                    $sub->where('formatted_number', 'like', "%{$term}%")
                        ->orWhere('subject', 'like', "%{$term}%")
                        ->orWhere('sender_name', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('document_type'), fn ($query) => $query->where('document_type', $request->input('document_type')))
            ->when($request->filled('sector_id'), fn ($query) => $query->where('destination_sector_id', $request->input('sector_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('protocoled_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('protocoled_at', '<=', $request->input('to')))
            ->latest('protocoled_at')
            ->paginate(15)
            ->withQueryString();

        return view('protocols.index', [
            'protocols' => $protocols,
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'statuses' => ProtocolStatus::options(),
            'priorities' => ProtocolPriority::options(),
            'documentTypes' => DocumentType::options(),
        ]);
    }

    /**
     * Caixa de entrada operacional: categorias de acompanhamento das
     * movimentações (FASE 2 — §8), respeitando o vínculo institucional e o
     * escopo do setor/comissão do usuário.
     */
    public function inbox(Request $request)
    {
        $user = $request->user();
        $canReceiveCommission = $user->hasPermission('protocols.receive_commission');

        $filters = $this->inboxFilters();
        $filter = $request->string('filtro')->toString();

        if (! array_key_exists($filter, $filters)) {
            $filter = 'aguardando';
        }

        $base = ProtocolMovement::query()->with([
            'protocol',
            'fromSector',
            'fromCommission',
            'fromUser',
            'toSector',
            'toCommission',
            'refusedBy',
            'divergences' => fn ($query) => $query->latest('id'),
        ]);

        $this->scopeInboxMovements($base, $user, $canReceiveCommission);

        $counts = [];

        foreach (array_keys($filters) as $key) {
            $countQuery = clone $base;
            $this->applyInboxFilter($countQuery, $key);
            $counts[$key] = $countQuery->count();
        }

        $listQuery = clone $base;
        $this->applyInboxFilter($listQuery, $filter);

        $movements = $listQuery->latest()->paginate(15)->withQueryString();

        return view('protocols.inbox', [
            'movements' => $movements,
            'legislative' => $this->legislativePending($user),
            'inboxFilters' => $filters,
            'inboxCounts' => $counts,
            'activeFilter' => $filter,
        ]);
    }

    /**
     * Categorias disponíveis na caixa de entrada.
     *
     * @return array<string,string>
     */
    private function inboxFilters(): array
    {
        return [
            'aguardando' => 'Aguardando recebimento',
            'recebidos' => 'Recebidos',
            'recusados' => 'Recusados',
            'divergencias' => 'Divergência aberta',
            'providencias' => 'Providência pendente',
            'proximos' => 'Próximos do vencimento',
            'vencidos' => 'Prazo vencido',
        ];
    }

    /**
     * Restringe as movimentações ao setor do usuário (origem ou destino) e/ou
     * às comissões que ele pode receber. Preserva o comportamento anterior
     * quando o usuário não possui vínculo de setor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProtocolMovement>  $query
     */
    private function scopeInboxMovements($query, $user, bool $canReceiveCommission): void
    {
        if (! $user->sector_id && ! $canReceiveCommission) {
            return;
        }

        $query->where(function ($sub) use ($user, $canReceiveCommission) {
            if ($user->sector_id) {
                $sub->where('to_sector_id', $user->sector_id)
                    ->orWhere('from_sector_id', $user->sector_id);
            }

            if ($canReceiveCommission) {
                $sub->orWhereNotNull('to_commission_id')
                    ->orWhereNotNull('from_commission_id');
            }
        });
    }

    /**
     * Aplica as condições de uma categoria da caixa de entrada.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProtocolMovement>  $query
     */
    private function applyInboxFilter($query, string $filter): void
    {
        switch ($filter) {
            case 'recebidos':
                $query->where('status', MovementStatus::RECEBIDO->value);
                break;

            case 'recusados':
                $query->where('status', MovementStatus::RECUSADO->value);
                break;

            case 'divergencias':
                $query->whereHas('divergences', fn ($divergence) => $divergence
                    ->whereIn('status', [DivergenceStatus::ABERTA->value, DivergenceStatus::EM_ANALISE->value]));
                break;

            case 'providencias':
                $query->whereHas('actions', fn ($action) => $action
                    ->whereIn('status', [ProtocolActionStatus::REGISTRADA->value, ProtocolActionStatus::EM_ANDAMENTO->value]));
                break;

            case 'proximos':
                $query->whereNotNull('due_at')
                    ->whereNull('received_at')
                    ->where('due_at', '>=', now())
                    ->where('due_at', '<=', now()->addDays(ProtocolMovement::NEAR_DUE_DAYS));
                break;

            case 'vencidos':
                $query->whereNotNull('due_at')
                    ->whereNull('received_at')
                    ->where('due_at', '<', now());
                break;

            case 'aguardando':
            default:
                $query->where('status', MovementStatus::AGUARDANDO_RECEBIMENTO->value);
                break;
        }
    }

    /**
     * Pendências legislativas visíveis ao usuário, conforme perfil e vínculo institucional.
     *
     * @return array<string,array{total:int,items:\Illuminate\Support\Collection<int,mixed>}>
     */
    private function legislativePending($user): array
    {
        $pending = [];

        if ($user->hasPermission('propositions.forward')) {
            $items = PropositionForwarding::with(['proposition', 'commission'])
                ->where('status', ForwardingStatus::AGUARDANDO_RECEBIMENTO->value)
                ->latest()->limit(10)->get();
            $pending['forwardings_receipt'] = ['total' => $items->count(), 'items' => $items];
        }

        if ($user->hasPermission('propositions.opinion')) {
            $items = PropositionForwarding::with(['proposition', 'commission'])
                ->where('status', ForwardingStatus::AGUARDANDO_PARECER->value)
                ->latest()->limit(10)->get();
            $pending['awaiting_opinion'] = ['total' => $items->count(), 'items' => $items];
        }

        if ($user->hasPermission('propositions.review')) {
            $items = Proposition::with('author')
                ->whereIn('status', [
                    PropositionStatus::ENVIADA->value,
                    PropositionStatus::EM_ANALISE->value,
                    PropositionStatus::REENVIADA->value,
                ])->latest()->limit(10)->get();
            $pending['awaiting_analysis'] = ['total' => $items->count(), 'items' => $items];
        }

        if ($user->hasPermission('sessions.agenda')) {
            $items = Proposition::with('author')
                ->whereIn('status', [
                    PropositionStatus::APROVADA->value,
                    PropositionStatus::REGISTRADA->value,
                ])->whereDoesntHave('agendaItems')->latest()->limit(10)->get();
            $pending['awaiting_agenda'] = ['total' => $items->count(), 'items' => $items];
        }

        return $pending;
    }

    /**
     * Formulário de cadastro.
     */
    public function create()
    {
        return view('protocols.create', [
            'documentTypes' => DocumentType::options(),
            'priorities' => ProtocolPriority::options(),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Grava um novo protocolo e seus anexos.
     */
    public function store(ProtocolRequest $request)
    {
        $data = $request->validated();
        unset($data['attachments']);

        $protocol = $this->service->create($data, $request->user());

        foreach ($request->file('attachments', []) as $file) {
            $meta = $this->attachments->store($file, $this->attachmentDirectory($protocol));

            $protocol->attachments()->create([
                ...$meta,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('success', 'Protocolo registrado com o número '.$protocol->formatted_number.'.');
    }

    /**
     * Detalhamento com dados, tramitação, histórico e anexos.
     */
    public function show(Protocol $protocol)
    {
        $protocol->load([
            'destinationSector',
            'currentSector',
            'currentCommission',
            'creator',
            'movements.fromSector',
            'movements.fromCommission',
            'movements.toSector',
            'movements.toCommission',
            'movements.fromUser',
            'movements.receiver',
            'movements.refusedBy',
            'movements.attachments',
            'actions.commission',
            'actions.sector',
            'actions.user',
            'actions.movement',
            'actions.divergence',
            'divergences.sector',
            'divergences.commission',
            'divergences.reporter',
            'divergences.movement',
            'movementCorrections.user',
            'movementCorrections.movement',
            'attachments.uploader',
        ]);

        // Reaproveita a coleção de movimentos já carregada ao calcular a
        // permanência (FASE 2 — §7), evitando consultas por movimentação.
        $protocol->movements->each->setRelation('protocol', $protocol);

        return view('protocols.show', [
            'protocol' => $protocol,
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'commissions' => Commission::where('is_active', true)->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    /**
     * Formulário de edição (número e ano não são editáveis).
     */
    public function edit(Protocol $protocol)
    {
        return view('protocols.edit', [
            'protocol' => $protocol,
            'priorities' => ProtocolPriority::options(),
            'statuses' => ProtocolStatus::options(),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Atualiza os dados editáveis do protocolo registrando a auditoria.
     */
    public function update(Request $request, Protocol $protocol)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_document' => ['nullable', 'string', 'max:20'],
            'sender_email' => ['nullable', 'email'],
            'sender_phone' => ['nullable', 'string', 'max:30'],
            'destination_sector_id' => ['required', Tenant::scopedExists('sectors')],
            'priority' => ['required', Rule::in(array_keys(ProtocolPriority::options()))],
            'status' => ['required', Rule::in(array_keys(ProtocolStatus::options()))],
        ]);

        $old = collect($data)
            ->mapWithKeys(function ($value, $key) use ($protocol) {
                $current = $protocol->getAttribute($key);

                return [$key => $current instanceof \BackedEnum ? $current->value : $current];
            })
            ->all();

        $protocol->update($data);

        AuditLogger::log(
            'protocol.updated',
            $protocol,
            $old,
            $data,
            'Protocolo '.$protocol->formatted_number.' atualizado.'
        );

        return redirect()->route('protocols.show', $protocol)
            ->with('success', 'Protocolo atualizado com sucesso.');
    }

    /**
     * Exclusão lógica do protocolo.
     */
    public function destroy(Protocol $protocol)
    {
        $protocol->delete();

        AuditLogger::log(
            'protocol.deleted',
            $protocol,
            ['formatted_number' => $protocol->formatted_number],
            [],
            'Protocolo '.$protocol->formatted_number.' excluído.'
        );

        return redirect()->route('protocols.index')
            ->with('success', 'Protocolo excluído com sucesso.');
    }

    /**
     * Anexa um arquivo ao protocolo (opcionalmente vinculado a um movimento).
     */
    public function storeAttachment(Request $request, Protocol $protocol)
    {
        $maxKb = (int) config('protocolo.max_upload_kb', 10240);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.$maxKb],
            'protocol_movement_id' => ['nullable', Tenant::scopedExists('protocol_movements')],
        ]);

        $meta = $this->attachments->store($request->file('file'), $this->attachmentDirectory($protocol));

        $protocol->attachments()->create([
            ...$meta,
            'protocol_movement_id' => $request->input('protocol_movement_id'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Anexo enviado com sucesso.');
    }

    /**
     * Download seguro de um anexo.
     */
    public function downloadAttachment(ProtocolAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Remove o anexo (arquivo físico + registro).
     */
    public function destroyAttachment(ProtocolAttachment $attachment)
    {
        $protocol = $attachment->protocol;
        $name = $attachment->original_name;

        $this->attachments->delete($attachment->file_path);
        $attachment->delete();

        AuditLogger::log(
            'protocol.attachment.deleted',
            $protocol,
            ['original_name' => $name],
            [],
            'Anexo removido do protocolo.'
        );

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    /**
     * Comprovante de protocolo em PDF.
     */
    public function receipt(Protocol $protocol)
    {
        $protocol->load(['destinationSector', 'currentSector', 'creator']);

        $filename = 'comprovante-'.str_replace('/', '-', $protocol->formatted_number).'.pdf';

        return Pdf::loadView('protocols.pdf.receipt', [
            'protocol' => $protocol,
            'institution' => $protocol->institution,
        ])->setPaper('a4')->stream($filename);
    }

    /**
     * Espelho de tramitação em PDF.
     */
    public function mirror(Protocol $protocol)
    {
        $protocol->load([
            'destinationSector',
            'currentSector',
            'creator',
            'movements.fromSector',
            'movements.toSector',
            'movements.fromUser',
            'movements.receiver',
        ]);

        $filename = 'espelho-'.str_replace('/', '-', $protocol->formatted_number).'.pdf';

        return Pdf::loadView('protocols.pdf.mirror', [
            'protocol' => $protocol,
            'institution' => $protocol->institution,
            'movements' => $protocol->movements,
        ])->setPaper('a4')->stream($filename);
    }

    /**
     * Diretório privado dos anexos por instituição/protocolo.
     */
    private function attachmentDirectory(Protocol $protocol): string
    {
        return config('protocolo.attachments_path').'/'.$protocol->institution_id.'/'.$protocol->id;
    }
}
