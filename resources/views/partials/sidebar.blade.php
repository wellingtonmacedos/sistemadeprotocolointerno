@php
    $user = auth()->user();
    $tenant = \App\Support\Tenant::get();
    $myCommissionIds = $user->activeCommissionIds();

    $links = [
        ['label' => 'Painel', 'route' => 'painel', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10', 'when' => true],
        ['label' => 'Protocolos', 'route' => 'protocols.index', 'icon' => 'M9 12h6m-6 4h6M7 4h10v16H7z', 'when' => $user->hasPermission('protocols.view')],
        ['label' => 'Proposições', 'route' => 'propositions.index', 'icon' => 'M4 6h16M4 12h16M4 18h10', 'when' => $user->hasPermission('propositions.view')],
        ['label' => 'Setores', 'route' => 'sectors.index', 'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01', 'when' => $user->hasPermission('sectors.view')],
        ['label' => 'Comissões', 'route' => 'commissions.index', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M12 12a4 4 0 100-8 4 4 0 000 8z', 'when' => $user->hasPermission('commissions.view')],
        ['label' => 'Minhas Comissões', 'route' => 'commissions.mine', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'when' => ! empty($myCommissionIds)],
        ['label' => 'Fluxos Legislativos', 'route' => 'legislative-flows.index', 'icon' => 'M4 5h16M4 12h10M4 19h7', 'when' => $user->hasPermission('legislative_flows.view')],
        ['label' => 'Sessões', 'route' => 'sessions.index', 'icon' => 'M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'when' => $user->hasPermission('sessions.view')],
        ['label' => 'Quórum', 'route' => 'quorum.index', 'icon' => 'M12 3v18M5 8h14M7 8l-2 6h4zM17 8l-2 6h4z', 'when' => $user->hasPermission('votings.quorum')],
        ['label' => 'Vereadores', 'route' => 'councilors.index', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'when' => $user->hasPermission('councilors.view')],
        ['label' => 'Usuários', 'route' => 'users.index', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M12 12a4 4 0 100-8 4 4 0 000 8z', 'when' => $user->hasPermission('users.view')],
        ['label' => 'Relatórios', 'route' => 'reports.index', 'icon' => 'M9 17v-6h6v6M5 21h14a1 1 0 001-1V4a1 1 0 00-1-1H5a1 1 0 00-1 1v16a1 1 0 001 1z', 'when' => $user->hasPermission('reports.view')],
        ['label' => 'Auditoria', 'route' => 'audit.index', 'icon' => 'M12 8v4l3 2M12 3a9 9 0 100 18 9 9 0 000-18z', 'when' => $user->hasPermission('audit.view')],
        ['label' => 'Central de Backup', 'route' => 'backups.index', 'icon' => 'M4 7v10a2 2 0 002 2h12a2 2 0 002-2V7M4 7a2 2 0 012-2h12a2 2 0 012 2M4 7h16M9 12h6', 'when' => $user->hasPermission('backups.view')],
    ];

    $adminLinks = [
        ['label' => 'Câmaras', 'route' => 'institutions.index', 'when' => $user->isSuperAdmin() || $user->hasPermission('institutions.manage')],
        ['label' => 'Perfis e Permissões', 'route' => 'roles.index', 'when' => $user->hasPermission('roles.view')],
    ];
@endphp

<aside id="app-sidebar"
       class="fixed inset-y-0 left-0 z-30 flex w-[264px] -translate-x-full flex-col border-r border-slate-800 bg-slate-900 text-slate-300 transition-transform lg:translate-x-0">
    <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-bold text-white shadow-elevated ring-1 ring-white/10">P</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">
                {{ $tenant?->name ?? 'Plataforma de Protocolos' }}
            </p>
            <p class="text-[11px] uppercase tracking-wide text-slate-500">
                {{ $tenant ? 'Câmara' : 'Super Admin' }}
            </p>
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach ($links as $link)
            @if ($link['when'])
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition
                          {{ request()->routeIs($link['route'].'*') ? 'bg-gradient-to-r from-brand-600 to-brand-500 text-white shadow-card' : 'hover:bg-slate-800 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/>
                    </svg>
                    <span class="truncate">{{ $link['label'] }}</span>
                </a>
            @endif
        @endforeach

        @if (collect($adminLinks)->contains(fn ($l) => $l['when']))
            <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-600">Administração</p>
            @foreach ($adminLinks as $link)
                @if ($link['when'])
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition
                              {{ request()->routeIs($link['route'].'*') ? 'bg-gradient-to-r from-brand-600 to-brand-500 text-white shadow-card' : 'hover:bg-slate-800 hover:text-white' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                        <span class="truncate">{{ $link['label'] }}</span>
                    </a>
                @endif
            @endforeach
        @endif
    </nav>

    @if ($user->actsAsCouncilor())
        <div class="border-t border-slate-800 p-3">
            <a href="{{ route('councilor.dashboard') }}"
               class="flex items-center gap-3 rounded-lg bg-emerald-600/90 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600">
                Painel do Vereador
            </a>
        </div>
    @endif
</aside>
