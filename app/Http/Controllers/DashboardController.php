<?php

namespace App\Http\Controllers;

use App\Enums\ForwardingStatus;
use App\Enums\MovementStatus;
use App\Enums\PostVotingStatus;
use App\Enums\PropositionStatus;
use App\Enums\ProtocolStatus;
use App\Models\Councilor;
use App\Models\PostVotingAction;
use App\Models\Proposition;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use App\Models\Sector;
use App\Support\Tenant;

class DashboardController extends Controller
{
    public function index()
    {
        $protocols = Protocol::query();
        $propositions = Proposition::query();

        $stats = [
            'protocols_total' => (clone $protocols)->count(),
            'protocols_in_progress' => (clone $protocols)->whereIn('status', [
                ProtocolStatus::RECEBIDO, ProtocolStatus::EM_ANALISE,
                ProtocolStatus::ENCAMINHADO, ProtocolStatus::AGUARDANDO_PROVIDENCIAS,
            ])->count(),
            'protocols_pending' => ProtocolMovement::where('status', MovementStatus::AGUARDANDO_RECEBIMENTO)->count(),
            'protocols_done' => (clone $protocols)->where('status', ProtocolStatus::CONCLUIDO)->count(),
            'propositions_in_review' => $propositions->whereIn('status', [
                PropositionStatus::ENVIADA, PropositionStatus::EM_ANALISE, PropositionStatus::REENVIADA,
            ])->count(),
            'propositions_approved' => (clone $propositions)->where('status', PropositionStatus::APROVADA)->count(),
            'propositions_registered' => (clone $propositions)->where('status', PropositionStatus::REGISTRADA)->count(),
            'sectors_total' => Sector::count(),
            'councilors_total' => Councilor::count(),
        ];

        $recentMovements = ProtocolMovement::with(['protocol', 'fromSector', 'toSector', 'fromUser'])
            ->latest()
            ->take(10)
            ->get();

        $recentProtocols = Protocol::with('destinationSector')->latest()->take(8)->get();
        $recentPropositions = Proposition::with('author')->latest()->take(8)->get();

        $byStatus = (clone $protocols)->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        // Etapa 11 — indicadores legislativos (sem remover os indicadores atuais).
        $legislative = [
            'protocoled' => Proposition::where('status', '!=', PropositionStatus::RASCUNHO->value)->count(),
            'in_analysis' => Proposition::where('status', PropositionStatus::EM_ANALISE->value)->count(),
            'awaiting_opinion' => $this->propositionsWithForwarding(ForwardingStatus::AGUARDANDO_PARECER),
            'in_commissions' => Proposition::whereHas('forwardings', function ($q) {
                $q->where('status', '!=', ForwardingStatus::CONCLUIDA->value);
            })->count(),
            'awaiting_agenda' => Proposition::whereIn('status', [
                PropositionStatus::APROVADA->value, PropositionStatus::REGISTRADA->value,
            ])->whereDoesntHave('agendaItems')->count(),
            'scheduled' => Proposition::whereHas('agendaItems')->count(),
            'approved' => Proposition::where('status', PropositionStatus::APROVADA->value)->count(),
            'rejected' => Proposition::where('status', PropositionStatus::REJEITADA->value)->count(),
            'awaiting_sanction' => PostVotingAction::where('status', PostVotingStatus::ENCAMINHADA_SANCAO->value)->count(),
            'promulgated' => PostVotingAction::where('status', PostVotingStatus::PROMULGADA->value)->count(),
            'published' => PostVotingAction::where('status', PostVotingStatus::PUBLICADA->value)->count(),
            'concluded' => PostVotingAction::where('status', PostVotingStatus::CONCLUIDA->value)->count(),
        ];

        return view('dashboard', [
            'stats' => $stats,
            'legislative' => $legislative,
            'recentMovements' => $recentMovements,
            'recentProtocols' => $recentProtocols,
            'recentPropositions' => $recentPropositions,
            'byStatus' => $byStatus,
            'tenant' => Tenant::get(),
        ]);
    }

    /** Conta proposições com encaminhamento em determinada situação. */
    private function propositionsWithForwarding(ForwardingStatus $status): int
    {
        return Proposition::whereHas('forwardings', fn ($q) => $q->where('status', $status->value))->count();
    }
}
