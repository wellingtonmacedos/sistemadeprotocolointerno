<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /** Permissões por grupo. */
    public const PERMISSIONS = [
        'institutions' => ['institutions.view', 'institutions.manage'],
        'users' => ['users.view', 'users.manage'],
        'sectors' => ['sectors.view', 'sectors.manage'],
        'councilors' => ['councilors.view', 'councilors.manage'],
        'commissions' => ['commissions.view', 'commissions.manage', 'commissions.composition'],
        'legislative_flows' => ['legislative_flows.view', 'legislative_flows.manage'],
        'sessions' => ['sessions.view', 'sessions.manage', 'sessions.agenda'],
        'votings' => ['votings.view', 'votings.register', 'votings.quorum', 'votings.post_actions'],
        'roles' => ['roles.view', 'roles.manage'],
        'protocols' => [
            'protocols.view', 'protocols.create', 'protocols.edit',
            'protocols.forward', 'protocols.receive', 'protocols.conclude', 'protocols.delete',
            'protocols.actions.view', 'protocols.actions.create', 'protocols.actions.manage',
            'protocols.receive_commission', 'protocols.dispatch_commission',
            'protocols.movements.correct', 'protocols.movements.correct_destination',
        ],
        'propositions' => [
            'propositions.view', 'propositions.create',
            'propositions.review', 'propositions.register',
            'propositions.forward', 'propositions.opinion',
        ],
        'backups' => [
            'backups.view', 'backups.create', 'backups.download',
            'backups.restore', 'backups.delete', 'backups.settings',
        ],
        'audit' => ['audit.view'],
        'reports' => ['reports.view'],
    ];

    /** Perfis e suas permissões (super_admin recebe todas). */
    public const ROLES = [
        'super_admin' => ['name' => 'Super Admin', 'all' => true],
        'administrador' => ['name' => 'Administrador da Câmara', 'permissions' => [
            'users.view', 'users.manage', 'sectors.view', 'sectors.manage',
            'councilors.view', 'councilors.manage', 'roles.view',
            'commissions.view', 'commissions.manage', 'commissions.composition',
            'legislative_flows.view', 'legislative_flows.manage',
            'sessions.view', 'sessions.manage', 'sessions.agenda',
            'votings.view', 'votings.register', 'votings.quorum', 'votings.post_actions',
            'protocols.view', 'protocols.create', 'protocols.edit', 'protocols.forward',
            'protocols.receive', 'protocols.conclude', 'protocols.delete',
            'protocols.actions.view', 'protocols.actions.create', 'protocols.actions.manage',
            'protocols.receive_commission', 'protocols.dispatch_commission',
            'protocols.movements.correct', 'protocols.movements.correct_destination',
            'propositions.view', 'propositions.create', 'propositions.review', 'propositions.register',
            'propositions.forward', 'propositions.opinion',
            'backups.view', 'backups.create', 'backups.download', 'backups.restore',
            'backups.delete', 'backups.settings', 'audit.view', 'reports.view',
        ]],
        'secretaria_diretoria' => ['name' => 'Secretaria / Diretoria', 'permissions' => [
            'users.view', 'sectors.view', 'councilors.view',
            'commissions.view', 'legislative_flows.view',
            'sessions.view', 'sessions.agenda',
            'votings.view', 'votings.post_actions',
            'protocols.view', 'protocols.create', 'protocols.edit', 'protocols.forward',
            'protocols.receive', 'protocols.conclude',
            'protocols.actions.view', 'protocols.actions.create', 'protocols.actions.manage',
            'protocols.receive_commission', 'protocols.dispatch_commission',
            'protocols.movements.correct',
            'propositions.view', 'propositions.create', 'propositions.review', 'propositions.register',
            'propositions.forward', 'propositions.opinion',
            'audit.view', 'reports.view',
        ]],
        'recepcao_protocolo' => ['name' => 'Recepção / Protocolo', 'permissions' => [
            'sectors.view', 'protocols.view', 'protocols.create', 'protocols.forward', 'protocols.receive',
            'protocols.actions.view', 'protocols.actions.create', 'protocols.dispatch_commission',
        ]],
        'servidor_setor' => ['name' => 'Servidor de Setor', 'permissions' => [
            'sectors.view', 'protocols.view', 'protocols.receive', 'protocols.forward', 'protocols.conclude',
            'protocols.actions.view', 'protocols.actions.create',
        ]],
        'presidente' => ['name' => 'Presidente', 'permissions' => [
            'protocols.view', 'propositions.view', 'reports.view', 'audit.view',
            'commissions.view', 'legislative_flows.view',
            'sessions.view', 'sessions.manage', 'sessions.agenda',
            'votings.view', 'votings.register', 'votings.quorum', 'votings.post_actions',
            'protocols.actions.view', 'protocols.actions.create',
            'protocols.receive_commission', 'protocols.dispatch_commission',
            'propositions.forward', 'propositions.opinion',
        ]],
        'vereador' => ['name' => 'Vereador', 'permissions' => [
            'protocols.view', 'protocols.actions.view', 'propositions.view', 'propositions.create',
            'commissions.view', 'sessions.view', 'votings.view',
        ]],
        'secretaria_legislativa' => ['name' => 'Secretaria Legislativa', 'permissions' => [
            'sectors.view', 'councilors.view', 'protocols.view',
            'commissions.view', 'commissions.composition', 'legislative_flows.view',
            'sessions.view', 'sessions.manage', 'sessions.agenda',
            'votings.view', 'votings.register', 'votings.quorum', 'votings.post_actions',
            'protocols.actions.view', 'protocols.actions.create',
            'protocols.receive_commission', 'protocols.dispatch_commission',
            'propositions.view', 'propositions.review', 'propositions.register', 'reports.view',
            'propositions.forward', 'propositions.opinion',
        ]],
    ];

    public function run(): void
    {
        $allSlugs = [];

        foreach (self::PERMISSIONS as $group => $slugs) {
            foreach ($slugs as $slug) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $this->humanize($slug), 'group' => $group]
                );
                $allSlugs[] = $slug;
            }
        }

        foreach (self::ROLES as $slug => $config) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $config['name'], 'is_system' => true]
            );

            $permissions = ($config['all'] ?? false) ? $allSlugs : ($config['permissions'] ?? []);
            $role->syncPermissions($permissions);
        }
    }

    private function humanize(string $slug): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $slug));
    }
}
