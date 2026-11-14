<?php

namespace App\Http\Controllers;

use App\Models\Councilor;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD administrativo de usuários (multi-Câmaras).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $roleId = $request->integer('role_id');

        $users = User::with(['role', 'sector'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($roleId, fn ($query) => $query->where('role_id', $roleId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'q' => $q,
            'roleId' => $roleId,
        ]);
    }

    public function create()
    {
        return view('users.form', $this->formData(null));
    }

    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'councilor_id' => ['nullable', Tenant::scopedExists('councilors')],
            'is_active' => ['boolean'],
            'institution_id' => [$isSuperAdmin ? 'required' : 'nullable', 'exists:institutions,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if (! $isSuperAdmin) {
            $data['institution_id'] = Tenant::id();
        }

        $user = User::create($data);

        AuditLogger::log('user.created', $user, [], $data, 'Usuário criado: '.$user->name);

        return redirect()->route('users.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'sector_id' => ['nullable', Tenant::scopedExists('sectors')],
            'councilor_id' => ['nullable', Tenant::scopedExists('councilors')],
            'is_active' => ['boolean'],
            'institution_id' => [$isSuperAdmin ? 'required' : 'nullable', 'exists:institutions,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // A senha só é alterada quando preenchida.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($isSuperAdmin) {
            $data['institution_id'] = $request->input('institution_id') ?: $user->institution_id;
        } else {
            $data['institution_id'] = Tenant::id();
        }

        $before = $user->only(array_keys($data));
        $user->update($data);

        AuditLogger::log('user.updated', $user, $before, $data, 'Usuário atualizado: '.$user->name);

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir a si próprio.');
        }

        $old = $user->getAttributes();
        $user->delete();

        AuditLogger::log('user.deleted', $user, $old, [], 'Usuário excluído: '.$user->name);

        return back()->with('success', 'Usuário excluído com sucesso.');
    }

    public function toggleBlock(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode bloquear a si próprio.');
        }

        $novoEstado = ! $user->is_active;
        $user->update(['is_active' => $novoEstado]);

        $action = $novoEstado ? 'user.unblocked' : 'user.blocked';
        AuditLogger::log($action, $user, ['is_active' => ! $novoEstado], ['is_active' => $novoEstado], $action === 'user.blocked' ? 'Usuário bloqueado: '.$user->name : 'Usuário desbloqueado: '.$user->name);

        return back()->with('success', $novoEstado ? 'Usuário desbloqueado com sucesso.' : 'Usuário bloqueado com sucesso.');
    }

    /** Dados compartilhados pelos formulários de criação e edição. */
    private function formData(?User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'sectors' => Sector::orderBy('name')->get(),
            'councilors' => Councilor::orderBy('name')->get(),
            'institutions' => auth()->user()->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ];
    }
}
