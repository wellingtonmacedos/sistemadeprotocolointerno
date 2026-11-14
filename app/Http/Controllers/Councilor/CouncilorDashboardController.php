<?php

namespace App\Http\Controllers\Councilor;

use App\Enums\PropositionStatus;
use App\Http\Controllers\Controller;
use App\Models\Proposition;
use Illuminate\Http\Request;

/**
 * Painel do Vereador: visão das proposições de autoria/coautoria do parlamentar.
 */
class CouncilorDashboardController extends Controller
{
    public function index()
    {
        $query = $this->propositionsQuery();

        $stats = [
            'total' => (clone $query)->count(),
            'rascunhos' => (clone $query)->where('status', PropositionStatus::RASCUNHO)->count(),
            'em_analise' => (clone $query)->whereIn('status', [
                PropositionStatus::ENVIADA,
                PropositionStatus::EM_ANALISE,
                PropositionStatus::REENVIADA,
            ])->count(),
            'aprovadas' => (clone $query)->where('status', PropositionStatus::APROVADA)->count(),
            'registradas' => (clone $query)->where('status', PropositionStatus::REGISTRADA)->count(),
        ];

        $recent = (clone $query)->with('author')->latest()->take(8)->get();

        return view('councilor.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    public function propositions(Request $request)
    {
        $query = $this->propositionsQuery()->with('author');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $propositions = $query->latest()->paginate(15)->withQueryString();

        return view('councilor.propositions', [
            'propositions' => $propositions,
            'statuses' => PropositionStatus::options(),
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Consulta base restrita às proposições do vereador autenticado (autor principal ou coautor).
     */
    protected function propositionsQuery()
    {
        $query = Proposition::query();
        $user = auth()->user();

        if ($user && $user->actsAsCouncilor() && $user->councilor_id) {
            $councilorId = $user->councilor_id;

            $query->where(function ($q) use ($councilorId) {
                $q->where('author_councilor_id', $councilorId)
                    ->orWhereHas('authors', fn ($a) => $a->where('councilors.id', $councilorId));
            });
        }

        return $query;
    }
}
