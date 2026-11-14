<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@protocolo.local');
        $password = env('SUPER_ADMIN_PASSWORD', 'Admin@12345');

        Tenant::bypass();

        $role = Role::where('slug', 'super_admin')->firstOrFail();

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Administrador',
                'password' => $password,
                'institution_id' => null,
                'role_id' => $role->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Tenant::bypass(false);

        $this->command?->info("Super Admin disponível: {$email}");
    }
}
