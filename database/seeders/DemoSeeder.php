<?php

namespace Database\Seeders;

use App\Models\Councilor;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dados de demonstração: uma Câmara com setores, vereadores e usuários por perfil.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::bypass();

        $institution = Institution::firstOrCreate(
            ['slug' => 'camara-municipal-demo'],
            [
                'name' => 'Câmara Municipal de Demonstração',
                'cnpj' => '00.000.000/0001-00',
                'city' => 'Cidade Demo',
                'state' => 'CE',
                'phone' => '(00) 0000-0000',
                'email' => 'contato@camarademo.local',
                'is_active' => true,
            ]
        );

        $sectorNames = [
            'Protocolo', 'Secretaria Legislativa', 'Presidência',
            'Assessoria Jurídica', 'Recursos Humanos', 'Financeiro',
            'Controle Interno', 'Arquivo',
        ];

        $sectors = collect($sectorNames)->mapWithKeys(function ($name) use ($institution) {
            $sector = Sector::firstOrCreate(
                ['institution_id' => $institution->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );

            return [$name => $sector];
        });

        $roleOf = fn (string $slug) => Role::where('slug', $slug)->first();

        $users = [
            ['Administrador Demo', 'admin@camarademo.local', 'administrador', null],
            ['Maria Protocolo', 'protocolo@camarademo.local', 'recepcao_protocolo', 'Protocolo'],
            ['João Secretaria', 'secretaria@camarademo.local', 'secretaria_legislativa', 'Secretaria Legislativa'],
            ['Carlos Vereador', 'vereador@camarademo.local', 'vereador', null],
        ];

        foreach ($users as [$name, $email, $roleSlug, $sectorName]) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'Senha@12345',
                    'institution_id' => $institution->id,
                    'role_id' => $roleOf($roleSlug)?->id,
                    'sector_id' => $sectorName ? $sectors[$sectorName]->id : null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        $councilors = [
            ['Carlos Vereador', 'PSC', 'vereador@camarademo.local'],
            ['Ana Souza', 'PTB', null],
            ['Pedro Lima', 'MDB', null],
            ['Juliana Alves', 'PSD', null],
        ];

        foreach ($councilors as [$name, $party, $email]) {
            Councilor::firstOrCreate(
                ['institution_id' => $institution->id, 'name' => $name],
                [
                    'party' => $party,
                    'email' => $email,
                    'legislature' => '2025-2028',
                    'is_active' => true,
                    'user_id' => $email ? User::withoutGlobalScopes()->where('email', $email)->value('id') : null,
                ]
            );
        }

        // Vínculo vereador -> councilor
        User::withoutGlobalScopes()->where('email', 'vereador@camarademo.local')->each(function (User $user) use ($institution) {
            $councilor = Councilor::where('institution_id', $institution->id)
                ->where('user_id', $user->id)->first();
            if ($councilor) {
                $user->update(['councilor_id' => $councilor->id]);
            }
        });

        Tenant::bypass(false);

        $this->command?->info('Dados de demonstração criados (Câmara Demo).');
    }
}
