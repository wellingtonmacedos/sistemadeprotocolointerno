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
        $email = config('protocolo.super_admin.email');
        $password = config('protocolo.super_admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->error('Super Admin não criado: defina SUPER_ADMIN_EMAIL e SUPER_ADMIN_PASSWORD no .env.');

            return;
        }

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
