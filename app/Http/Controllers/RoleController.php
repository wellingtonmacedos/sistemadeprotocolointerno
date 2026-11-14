<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestão de perfis e permissões.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('roles.index', ['roles' => $roles]);
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        $permissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return view('roles.edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $selecionadas = $data['permissions'] ?? [];
        $anteriores = $role->permissions->pluck('slug')->all();

        $role->syncPermissions($selecionadas);

        AuditLogger::log(
            'role.updated',
            $role,
            ['permissions' => $anteriores],
            ['permissions' => $selecionadas],
            'Permissões do perfil atualizadas: '.$role->name,
        );

        return redirect()->route('roles.index')->with('success', 'Permissões do perfil atualizadas com sucesso.');
    }
}
