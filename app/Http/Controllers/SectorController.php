<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Protocol;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD administrativo de setores (multi-Câmaras).
 */
class SectorController extends Controller
{
    public function index()
    {
        $sectors = Sector::with('responsible')
            ->orderBy('name')
            ->paginate(15);

        return view('sectors.index', ['sectors' => $sectors]);
    }

    public function create()
    {
        return view('sectors.form', [
            'sector' => null,
            'users' => User::orderBy('name')->get(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'responsible_user_id' => ['nullable', Tenant::scopedExists('users')],
            'is_active' => ['boolean'],
            'can_receive' => ['boolean'],
            'can_dispatch' => ['boolean'],
            'can_attach' => ['boolean'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['display_order'] = (int) $request->input('display_order', 0);
        $data['can_receive'] = $request->boolean('can_receive', true);
        $data['can_dispatch'] = $request->boolean('can_dispatch', true);
        $data['can_attach'] = $request->boolean('can_attach', true);
        $data['institution_id'] = auth()->user()->isSuperAdmin()
            ? ($request->input('institution_id') ?: null)
            : Tenant::id();

        $sector = Sector::create($data);

        AuditLogger::log('sector.created', $sector, [], $data, 'Setor criado: '.$sector->name);

        return redirect()->route('sectors.index')->with('success', 'Setor criado com sucesso.');
    }

    public function edit(Sector $sector)
    {
        return view('sectors.form', [
            'sector' => $sector,
            'users' => User::orderBy('name')->get(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, Sector $sector)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'responsible_user_id' => ['nullable', Tenant::scopedExists('users')],
            'is_active' => ['boolean'],
            'can_receive' => ['boolean'],
            'can_dispatch' => ['boolean'],
            'can_attach' => ['boolean'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['display_order'] = (int) $request->input('display_order', 0);
        $data['can_receive'] = $request->boolean('can_receive', true);
        $data['can_dispatch'] = $request->boolean('can_dispatch', true);
        $data['can_attach'] = $request->boolean('can_attach', true);

        if (auth()->user()->isSuperAdmin()) {
            $data['institution_id'] = $request->input('institution_id') ?: $sector->institution_id;
        } else {
            $data['institution_id'] = Tenant::id();
        }

        $before = $sector->only(array_keys($data));
        $sector->update($data);

        AuditLogger::log('sector.updated', $sector, $before, $data, 'Setor atualizado: '.$sector->name);

        return redirect()->route('sectors.index')->with('success', 'Setor atualizado com sucesso.');
    }

    public function destroy(Sector $sector)
    {
        $temProtocolos = Protocol::where(function ($query) use ($sector) {
            $query->where('destination_sector_id', $sector->id)
                ->orWhere('current_sector_id', $sector->id);
        })->exists();

        if ($temProtocolos) {
            return back()->with('error', 'Não é possível excluir este setor pois existem protocolos vinculados a ele.');
        }

        $old = $sector->getAttributes();
        $sector->delete();

        AuditLogger::log('sector.deleted', $sector, $old, [], 'Setor excluído: '.$sector->name);

        return back()->with('success', 'Setor excluído com sucesso.');
    }
}
