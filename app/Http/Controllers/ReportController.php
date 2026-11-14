<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\ForwardingStatus;
use App\Enums\PostVotingStatus;
use App\Enums\PropositionStatus;
use App\Enums\ProtocolStatus;
use App\Models\Commission;
use App\Models\Councilor;
use App\Models\LegislativeSession;
use App\Models\Proposition;
use App\Models\Protocol;
use App\Models\Sector;
use Illuminate\Http\Request;

/**
 * Relatórios operacionais e gerenciais.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);

        $protocolsByStatus = Protocol::where('year', $year)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $protocolsByType = Protocol::where('year', $year)
            ->selectRaw('document_type, count(*) as total')->groupBy('document_type')->pluck('total', 'document_type');

        $protocolsBySector = Protocol::where('year', $year)
            ->whereNotNull('destination_sector_id')
            ->selectRaw('destination_sector_id, count(*) as total')
            ->groupBy('destination_sector_id')->pluck('total', 'destination_sector_id');

        $propositionsByModality = Proposition::where('year', $year)
            ->selectRaw('modality, count(*) as total')->groupBy('modality')->pluck('total', 'modality');

        return view('reports.index', [
            'year' => $year,
            'years' => $this->availableYears(),
            'totals' => [
                'protocols' => Protocol::where('year', $year)->count(),
                'propositions' => Proposition::where('year', $year)->count(),
                'concluded' => Protocol::where('year', $year)->where('status', ProtocolStatus::CONCLUIDO)->count(),
                'registered' => Proposition::where('year', $year)->where('status', PropositionStatus::REGISTRADA)->count(),
            ],
            'protocolsByStatus' => $protocolsByStatus,
            'protocolsByType' => $protocolsByType,
            'protocolsBySector' => $protocolsBySector,
            'sectors' => Sector::orderBy('name')->pluck('name', 'id'),
            'propositionsByModality' => $propositionsByModality,
        ]);
    }

    public function protocols(Request $request)
    {
        $query = Protocol::with(['destinationSector', 'currentSector', 'creator'])->latest();

        if ($from = $request->input('from')) {
            $query->whereDate('protocoled_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('protocoled_at', '<=', $to);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('document_type')) {
            $query->where('document_type', $type);
        }

        if ($sector = $request->input('sector_id')) {
            $query->where('destination_sector_id', $sector);
        }

        $protocols = $query->paginate(25)->withQueryString();

        return view('reports.protocols', [
            'protocols' => $protocols,
            'statuses' => ProtocolStatus::options(),
            'types' => DocumentType::options(),
            'sectors' => Sector::orderBy('name')->get(),
            'filters' => $request->only(['from', 'to', 'status', 'document_type', 'sector_id']),
            'total' => $protocols->total(),
        ]);
    }

    public function propositions(Request $request)
    {
        $query = Proposition::with(['author', 'reviewer'])->latest();

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($modality = $request->input('modality')) {
            $query->where('modality', $modality);
        }

        if ($author = $request->input('author_councilor_id')) {
            $query->where(function ($q) use ($author) {
                $q->where('author_councilor_id', $author)
                    ->orWhereHas('authors', fn ($a) => $a->whereKey($author));
            });
        }

        if ($commission = $request->input('commission_id')) {
            $query->whereHas('forwardings', fn ($q) => $q->where('commission_id', $commission));
        }

        if ($session = $request->input('session_id')) {
            $query->whereHas('agendaItems', fn ($q) => $q->where('session_id', $session));
        }

        if ($stage = $request->input('stage')) {
            $this->applyStageFilter($query, $stage);
        }

        $propositions = $query->paginate(25)->withQueryString();

        return view('reports.propositions', [
            'propositions' => $propositions,
            'statuses' => PropositionStatus::options(),
            'modalities' => \App\Enums\PropositionModality::options(),
            'councilors' => Councilor::orderBy('name')->get(),
            'commissions' => Commission::orderBy('name')->get(),
            'sessions' => LegislativeSession::orderByDesc('date')->limit(100)->get(),
            'stages' => $this->legislativeStages(),
            'filters' => $request->only(['from', 'to', 'status', 'modality', 'author_councilor_id', 'commission_id', 'session_id', 'stage']),
            'total' => $propositions->total(),
        ]);
    }

    /** Etapas legislativas disponíveis para filtro (alinhadas ao dashboard). @return array<string,string> */
    private function legislativeStages(): array
    {
        return [
            'protocolada' => 'Protocolada',
            'em_analise' => 'Em análise',
            'aguardando_parecer' => 'Aguardando parecer',
            'em_comissoes' => 'Em comissões',
            'aguardando_pauta' => 'Aguardando pauta',
            'pautada' => 'Pautada',
            'aprovada' => 'Aprovada',
            'rejeitada' => 'Rejeitada',
            'aguardando_sancao' => 'Aguardando sanção',
            'promulgada' => 'Promulgada',
            'publicada' => 'Publicada',
            'concluida' => 'Concluída',
        ];
    }

    /** Aplica o filtro por etapa legislativa à consulta de proposições. */
    private function applyStageFilter($query, string $stage): void
    {
        match ($stage) {
            'protocolada' => $query->where('status', '!=', PropositionStatus::RASCUNHO->value),
            'em_analise' => $query->where('status', PropositionStatus::EM_ANALISE->value),
            'aguardando_parecer' => $query->whereHas('forwardings', fn ($q) => $q->where('status', ForwardingStatus::AGUARDANDO_PARECER->value)),
            'em_comissoes' => $query->whereHas('forwardings', fn ($q) => $q->where('status', '!=', ForwardingStatus::CONCLUIDA->value)),
            'aguardando_pauta' => $query->whereIn('status', [
                PropositionStatus::APROVADA->value,
                PropositionStatus::REGISTRADA->value,
            ])->whereDoesntHave('agendaItems'),
            'pautada' => $query->whereHas('agendaItems'),
            'aprovada' => $query->where('status', PropositionStatus::APROVADA->value),
            'rejeitada' => $query->where('status', PropositionStatus::REJEITADA->value),
            'aguardando_sancao' => $query->whereHas('postVotingActions', fn ($q) => $q->where('status', PostVotingStatus::ENCAMINHADA_SANCAO->value)),
            'promulgada' => $query->whereHas('postVotingActions', fn ($q) => $q->where('status', PostVotingStatus::PROMULGADA->value)),
            'publicada' => $query->whereHas('postVotingActions', fn ($q) => $q->where('status', PostVotingStatus::PUBLICADA->value)),
            'concluida' => $query->whereHas('postVotingActions', fn ($q) => $q->where('status', PostVotingStatus::CONCLUIDA->value)),
            default => null,
        };
    }

    /** @return array<int,int> */
    private function availableYears(): array
    {
        $protocolYears = Protocol::select('year')->distinct()->pluck('year')->all();
        $propositionYears = Proposition::select('year')->distinct()->pluck('year')->all();

        $years = array_unique(array_merge($protocolYears, $propositionYears, [now()->year]));
        rsort($years);

        return array_values(array_filter($years));
    }
}
