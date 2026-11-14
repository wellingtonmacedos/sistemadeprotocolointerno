<?php

namespace App\Http\Controllers;

use App\Enums\LegislativeStepType;
use App\Enums\PropositionModality;
use App\Models\Commission;
use App\Models\Institution;
use App\Models\LegislativeFlow;
use App\Models\LegislativeFlowVersion;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LegislativeFlowService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Configuração e versionamento dos fluxos legislativos por instituição/modalidade.
 */
class LegislativeFlowController extends Controller
{
    public function __construct(private readonly LegislativeFlowService $flows)
    {
    }

    public function index()
    {
        $flows = LegislativeFlow::with(['versions' => fn ($q) => $q->withCount('steps')])
            ->orderBy('modality')
            ->paginate(15);

        return view('legislative_flows.index', ['flows' => $flows]);
    }

    public function create()
    {
        return view('legislative_flows.form', [
            'flow' => null,
            'usedModalities' => $this->usedModalities(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->resolveInstitutionId($request, null);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'modality' => [
                'required',
                Rule::in(array_keys(PropositionModality::options())),
                Rule::unique('legislative_flows', 'modality')
                    ->where(fn ($q) => $q->where('institution_id', $institutionId)),
            ],
            'description' => ['nullable', 'string'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $flow = DB::transaction(function () use ($data, $institutionId) {
            $flow = LegislativeFlow::create([
                'institution_id' => $institutionId,
                'name' => $data['name'],
                'modality' => $data['modality'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            $this->flows->createVersion($flow, 'rascunho');

            return $flow;
        });

        AuditLogger::log('legislative_flow.created', $flow, [], $data, 'Fluxo legislativo criado: '.$flow->name);

        return redirect()->route('legislative-flows.show', $flow)
            ->with('success', 'Fluxo criado com a configuração inicial sugerida. Revise as etapas e publique.');
    }

    public function show(LegislativeFlow $flow)
    {
        $flow->load(['versions' => fn ($q) => $q->withCount('steps')]);

        $current = $flow->currentVersion();
        $draft = $flow->versions()->where('status', 'rascunho')->first();

        $version = $draft ?? $current;

        return view('legislative_flows.show', [
            'flow' => $flow,
            'current' => $current,
            'draft' => $draft,
            'version' => $version,
            'steps' => $version?->steps ?? collect(),
        ]);
    }

    public function publish(LegislativeFlow $flow, LegislativeFlowVersion $version)
    {
        abort_unless($version->legislative_flow_id === $flow->id, 404);

        if ($version->steps()->count() === 0) {
            return back()->with('error', 'Não é possível publicar um fluxo sem etapas configuradas.');
        }

        $this->flows->publish($version);

        AuditLogger::log('legislative_flow.published', $flow, [], [
            'version' => $version->version,
        ], 'Versão publicada: v'.$version->version.' — '.$flow->name);

        return back()->with('success', 'Versão v'.$version->version.' publicada como vigente.');
    }

    public function newVersion(LegislativeFlow $flow)
    {
        $base = $flow->currentVersion() ?? $flow->versions()->first();

        $version = $this->flows->createVersion($flow, 'rascunho', $base);

        AuditLogger::log('legislative_flow.version_created', $flow, [], [
            'version' => $version->version,
        ], 'Nova versão (rascunho) criada: v'.$version->version.' — '.$flow->name);

        return redirect()->route('legislative-flows.show', $flow)
            ->with('success', 'Nova versão v'.$version->version.' criada em rascunho. As proposições em andamento mantêm a versão anterior.');
    }

    public function editSteps(LegislativeFlow $flow, LegislativeFlowVersion $version)
    {
        abort_unless($version->legislative_flow_id === $flow->id, 404);

        return view('legislative_flows.steps', [
            'flow' => $flow,
            'version' => $version,
            'steps' => $version->steps,
            'stepTypes' => LegislativeStepType::options(),
            'sectors' => Sector::orderBy('name')->get(),
            'commissions' => Commission::where('is_active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function updateSteps(Request $request, LegislativeFlow $flow, LegislativeFlowVersion $version)
    {
        abort_unless($version->legislative_flow_id === $flow->id, 404);

        if ($version->isPublished()) {
            return back()->with('error', 'Versões publicadas são imutáveis. Crie uma nova versão para alterar as etapas.');
        }

        $data = $request->validate([
            'steps' => ['array'],
            'steps.*.name' => ['nullable', 'string', 'max:255'],
            'steps.*.step_type' => ['nullable', Rule::in(array_keys(LegislativeStepType::options()))],
            'steps.*.sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'steps.*.commission_id' => ['nullable', Tenant::scopedExists('commissions')],
            'steps.*.responsible_user_id' => ['nullable', Tenant::scopedExists('users')],
            'steps.*.deadline_days' => ['nullable', 'integer', 'min:0'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.advance_condition' => ['nullable', 'string'],
            'steps.*.conclusion_status' => ['nullable', 'string', 'max:40'],
        ]);

        $submitted = collect($data['steps'] ?? [])
            ->filter(fn ($s) => filled($s['name'] ?? null))
            ->values();

        DB::transaction(function () use ($version, $submitted, $request) {
            $keptIds = [];

            foreach ($submitted as $i => $row) {
                $payload = [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'step_type' => $row['step_type'] ?? 'analise',
                    'sector_id' => $row['sector_id'] ?? null,
                    'commission_id' => $row['commission_id'] ?? null,
                    'responsible_user_id' => $row['responsible_user_id'] ?? null,
                    'deadline_days' => $row['deadline_days'] ?? null,
                    'is_required' => (bool) ($row['is_required'] ?? false),
                    'can_skip' => (bool) ($row['can_skip'] ?? false),
                    'requires_document' => (bool) ($row['requires_document'] ?? false),
                    'advance_condition' => $row['advance_condition'] ?? null,
                    'allows_return' => (bool) ($row['allows_return'] ?? false),
                    'is_conclusive' => (bool) ($row['is_conclusive'] ?? false),
                    'conclusion_status' => $row['conclusion_status'] ?? null,
                    'position' => $i + 1,
                ];

                $id = $row['id'] ?? null;
                $existing = $id ? $version->steps()->whereKey($id)->first() : null;

                if ($existing) {
                    $existing->update($payload);
                    $keptIds[] = $existing->id;
                } else {
                    $created = $version->steps()->create(array_merge($payload, [
                        'institution_id' => $version->institution_id,
                    ]));
                    $keptIds[] = $created->id;
                }
            }

            $version->steps()->whereNotIn('id', $keptIds)->delete();
        });

        AuditLogger::log('legislative_flow.steps_updated', $flow, [], [
            'version' => $version->version,
            'steps' => $submitted->count(),
        ], 'Etapas atualizadas: v'.$version->version.' — '.$flow->name);

        return redirect()->route('legislative-flows.show', $flow)
            ->with('success', 'Etapas atualizadas com sucesso.');
    }

    public function destroy(LegislativeFlow $flow)
    {
        $old = $flow->getAttributes();
        $flow->delete();

        AuditLogger::log('legislative_flow.deleted', $flow, $old, [], 'Fluxo legislativo excluído: '.$flow->name);

        return redirect()->route('legislative-flows.index')->with('success', 'Fluxo excluído com sucesso.');
    }

    /** @return array<int,string> */
    private function usedModalities(): array
    {
        return LegislativeFlow::query()->pluck('modality')->all();
    }

    private function resolveInstitutionId(Request $request, ?LegislativeFlow $flow): ?int
    {
        if (auth()->user()->isSuperAdmin()) {
            return $request->input('institution_id')
                ? (int) $request->input('institution_id')
                : $flow?->institution_id;
        }

        return Tenant::id();
    }
}
