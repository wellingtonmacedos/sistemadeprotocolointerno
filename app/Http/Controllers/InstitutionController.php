<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD administrativo de Câmaras (instituições).
 */
class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::orderBy('name')->paginate(15);

        return view('institutions.index', ['institutions' => $institutions]);
    }

    public function create()
    {
        return view('institutions.form', ['institution' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20', Rule::unique('institutions', 'cnpj')],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institutions', 'local');
        }

        unset($data['logo']);

        $institution = Institution::create($data);

        AuditLogger::log('institution.created', $institution, [], $data, 'Câmara criada: '.$institution->name);

        return redirect()->route('institutions.index')->with('success', 'Câmara criada com sucesso.');
    }

    public function edit(Institution $institution)
    {
        return view('institutions.form', ['institution' => $institution]);
    }

    public function update(Request $request, Institution $institution)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20', Rule::unique('institutions', 'cnpj')->ignore($institution->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name'], $institution->id);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institutions', 'local');
        }

        unset($data['logo']);

        $before = $institution->only(array_keys($data));
        $institution->update($data);

        AuditLogger::log('institution.updated', $institution, $before, $data, 'Câmara atualizada: '.$institution->name);

        return redirect()->route('institutions.index')->with('success', 'Câmara atualizada com sucesso.');
    }

    public function destroy(Institution $institution)
    {
        if ($institution->users()->exists() || $institution->protocols()->exists()) {
            return back()->with('error', 'Não é possível excluir esta câmara pois existem usuários ou protocolos vinculados.');
        }

        $old = $institution->getAttributes();
        $institution->delete();

        AuditLogger::log('institution.deleted', $institution, $old, [], 'Câmara excluída: '.$institution->name);

        return back()->with('success', 'Câmara excluída com sucesso.');
    }

    /** Gera um slug único a partir do nome, acrescentando sufixo numérico se necessário. */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'camara';
        $slug = $base;
        $sufixo = 2;

        while (
            Institution::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$sufixo;
            $sufixo++;
        }

        return $slug;
    }
}
