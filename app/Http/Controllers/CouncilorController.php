<?php

namespace App\Http\Controllers;

use App\Models\Councilor;
use App\Models\Institution;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * CRUD administrativo de vereadores (multi-Câmaras).
 */
class CouncilorController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();

        $councilors = Councilor::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('party', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('councilors.index', [
            'councilors' => $councilors,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('councilors.form', [
            'councilor' => null,
            'users' => User::orderBy('name')->get(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mandate' => ['nullable', 'string', 'max:255'],
            'legislature' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', Tenant::scopedExists('users')],
            'is_active' => ['boolean'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['institution_id'] = auth()->user()->isSuperAdmin()
            ? ($request->input('institution_id') ?: null)
            : Tenant::id();

        $councilor = Councilor::create($data);

        AuditLogger::log('councilor.created', $councilor, [], $data, 'Vereador criado: '.$councilor->name);

        return redirect()->route('councilors.index')->with('success', 'Vereador cadastrado com sucesso.');
    }

    public function edit(Councilor $councilor)
    {
        return view('councilors.form', [
            'councilor' => $councilor,
            'users' => User::orderBy('name')->get(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, Councilor $councilor)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mandate' => ['nullable', 'string', 'max:255'],
            'legislature' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', Tenant::scopedExists('users')],
            'is_active' => ['boolean'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if (auth()->user()->isSuperAdmin()) {
            $data['institution_id'] = $request->input('institution_id') ?: $councilor->institution_id;
        } else {
            $data['institution_id'] = Tenant::id();
        }

        $before = $councilor->only(array_keys($data));
        $councilor->update($data);

        AuditLogger::log('councilor.updated', $councilor, $before, $data, 'Vereador atualizado: '.$councilor->name);

        return redirect()->route('councilors.index')->with('success', 'Vereador atualizado com sucesso.');
    }

    public function destroy(Councilor $councilor)
    {
        $old = $councilor->getAttributes();
        $councilor->delete();

        AuditLogger::log('councilor.deleted', $councilor, $old, [], 'Vereador excluído: '.$councilor->name);

        return back()->with('success', 'Vereador excluído com sucesso.');
    }
}
