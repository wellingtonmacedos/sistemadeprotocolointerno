<?php

namespace App\Http\Controllers;

use App\Enums\CommissionMemberRole;
use App\Enums\CommissionType;
use App\Enums\ForwardingStatus;
use App\Enums\PropositionModality;
use App\Models\Commission;
use App\Models\Councilor;
use App\Models\Institution;
use App\Models\PropositionForwarding;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de comissões legislativas (independente dos setores administrativos).
 */
class CommissionController extends Controller
{
    public function index()
    {
        $commissions = Commission::with(['currentMembers.councilor'])
            ->withCount('currentMembers')
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(15);

        return view('commissions.index', ['commissions' => $commissions]);
    }

    /**
     * "Minhas Comissões": comissões em que o usuário atua hoje (via vereador
     * vinculado) e as pendências de parecer/recebimento sob sua responsabilidade.
     */
    public function mine()
    {
        $user = auth()->user();

        $memberships = $user->activeCommissionMemberships()
            ->with('commission')
            ->get()
            ->groupBy('commission_id');

        $commissions = $memberships
            ->map(fn ($group) => $group->first()->commission)
            ->filter()
            ->sortBy('name')
            ->values();

        $commissionIds = $commissions->pluck('id')->all();

        $pendingForwardings = $commissionIds === []
            ? collect()
            : PropositionForwarding::query()
                ->with(['proposition', 'commission'])
                ->whereIn('commission_id', $commissionIds)
                ->whereIn('status', [
                    ForwardingStatus::AGUARDANDO_RECEBIMENTO,
                    ForwardingStatus::EM_ANALISE,
                    ForwardingStatus::AGUARDANDO_PARECER,
                ])
                ->orderBy('deadline')
                ->get();

        return view('commissions.mine', [
            'memberships' => $memberships,
            'commissions' => $commissions,
            'pendingForwardings' => $pendingForwardings,
        ]);
    }

    public function create()
    {
        return view('commissions.form', [
            'commission' => null,
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $data['institution_id'] = $this->resolveInstitutionId($request, null);
        $data = $this->normalize($request, $data);

        $commission = Commission::create($data);

        AuditLogger::log('commission.created', $commission, [], $data, 'Comissão criada: '.$commission->name);

        return redirect()->route('commissions.show', $commission)->with('success', 'Comissão criada com sucesso.');
    }

    public function show(Commission $commission)
    {
        $commission->load(['members.councilor']);

        return view('commissions.show', [
            'commission' => $commission,
            'councilors' => Councilor::where('is_active', true)->orderBy('name')->get(),
            'roles' => CommissionMemberRole::options(),
            'types' => CommissionType::options(),
        ]);
    }

    public function edit(Commission $commission)
    {
        return view('commissions.form', [
            'commission' => $commission,
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, Commission $commission)
    {
        $data = $request->validate($this->rules($request, $commission));

        $data['institution_id'] = $this->resolveInstitutionId($request, $commission);
        $data = $this->normalize($request, $data);

        $before = $commission->only(array_keys($data));
        $commission->update($data);

        AuditLogger::log('commission.updated', $commission, $before, $data, 'Comissão atualizada: '.$commission->name);

        return redirect()->route('commissions.show', $commission)->with('success', 'Comissão atualizada com sucesso.');
    }

    public function destroy(Commission $commission)
    {
        $old = $commission->getAttributes();
        $commission->delete();

        AuditLogger::log('commission.deleted', $commission, $old, [], 'Comissão excluída: '.$commission->name);

        return redirect()->route('commissions.index')->with('success', 'Comissão excluída com sucesso.');
    }

    /** @return array<string,mixed> */
    private function rules(Request $request, ?Commission $commission = null): array
    {
        $institutionId = $this->resolveInstitutionId($request, $commission);

        return [
            'name' => ['required', 'string', 'max:255'],
            'acronym' => [
                'required', 'string', 'max:30',
                Rule::unique('commissions', 'acronym')
                    ->where(fn ($q) => $q->where('institution_id', $institutionId))
                    ->ignore($commission?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'competences' => ['nullable', 'string'],
            'type' => ['required', Rule::in(array_keys(CommissionType::options()))],
            'allowed_modalities' => ['nullable', 'array'],
            'allowed_modalities.*' => [Rule::in(array_keys(PropositionModality::options()))],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ];
    }

    /** @param array<string,mixed> $data
     *  @return array<string,mixed> */
    private function normalize(Request $request, array $data): array
    {
        $data['is_active'] = $request->boolean('is_active');
        $data['display_order'] = (int) $request->input('display_order', 0);
        $data['allowed_modalities'] = array_values($request->input('allowed_modalities', []) ?: []) ?: null;

        return $data;
    }

    private function resolveInstitutionId(Request $request, ?Commission $commission): ?int
    {
        if (auth()->user()->isSuperAdmin()) {
            return $request->input('institution_id')
                ? (int) $request->input('institution_id')
                : $commission?->institution_id;
        }

        return Tenant::id();
    }
}
