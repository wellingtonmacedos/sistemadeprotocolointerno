<?php

namespace Tests;

use App\Models\Councilor;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Support\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * O Tenant é estático e sobrevive entre testes; garantimos estado limpo
     * para que cada teste comece sem instituição ativa nem bypass de escopo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tenant::set(null);
        Tenant::bypass(false);
    }

    /** Popula perfis e permissões (necessário para RBAC e super_admin). */
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeInstitution(array $attributes = []): Institution
    {
        return Institution::withoutGlobalScopes()->create(array_merge([
            'name' => 'Câmara '.Str::random(6),
            'slug' => 'camara-'.Str::lower(Str::random(8)),
            'city' => 'Cidade',
            'state' => 'CE',
            'is_active' => true,
        ], $attributes));
    }

    protected function makeSector(Institution $institution, string $name = 'Protocolo'): Sector
    {
        return Sector::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'is_active' => true,
        ]);
    }

    protected function makeUser(Institution $institution, string $roleSlug = 'administrador', array $attributes = []): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::withoutGlobalScopes()->create(array_merge([
            'name' => 'Usuário '.Str::random(5),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => 'Senha@12345',
            'institution_id' => $institution->id,
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function makeSuperAdmin(array $attributes = []): User
    {
        $role = Role::where('slug', 'super_admin')->firstOrFail();

        return User::withoutGlobalScopes()->create(array_merge([
            'name' => 'Super Admin',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => 'Senha@12345',
            'institution_id' => null,
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function makeCouncilor(Institution $institution, array $attributes = []): Councilor
    {
        return Councilor::withoutGlobalScopes()->create(array_merge([
            'institution_id' => $institution->id,
            'name' => 'Vereador '.Str::random(5),
            'party' => 'PSD',
            'legislature' => '2025-2028',
            'is_active' => true,
        ], $attributes));
    }
}
