<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    <script>
        // Aplica o tema antes da pintura para evitar "flash" de tema incorreto.
        (function () {
            try {
                var stored = localStorage.getItem('spi-theme');
                var dark = stored
                    ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {
                /* ignora */
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-body antialiased">
<div class="flex min-h-screen">

    @include('partials.sidebar')

    <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-[264px]">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-line bg-surface/90 px-4 backdrop-blur md:px-6">
            <button type="button" data-sidebar-toggle
                    class="rounded-lg p-2 text-muted hover:bg-surface-2 hover:text-body lg:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-semibold text-body">@yield('title', 'Painel')</h1>
                @hasSection('subtitle')
                    <p class="truncate text-xs text-muted">@yield('subtitle')</p>
                @endif
            </div>

            @hasSection('actions')
                <div class="hidden items-center gap-2 md:flex">@yield('actions')</div>
            @endif

            <button type="button" data-theme-toggle
                    class="rounded-lg p-2 text-muted hover:bg-surface-2 hover:text-body"
                    aria-label="Alternar tema" aria-pressed="false">
                <svg data-theme-icon="moon" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <svg data-theme-icon="sun" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M5.64 5.64l1.42 1.42m9.88 9.88l1.42 1.42M3 12h2m14 0h2M5.64 18.36l1.42-1.42m9.88-9.88l1.42-1.42M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
            </button>

            <div class="relative" data-dropdown>
                <button type="button" data-dropdown-trigger
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-semibold text-white shadow-sm">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                    <span class="hidden text-left md:block">
                        <span class="block text-sm font-medium leading-tight">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-muted">{{ auth()->user()->role?->name ?? '—' }}</span>
                    </span>
                </button>
                <div data-dropdown-menu
                     class="absolute right-0 mt-2 hidden w-56 rounded-xl border border-line bg-surface py-1 shadow-elevated">
                    <div class="border-b border-line px-4 py-2">
                        <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-muted">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="{{ route('perfil.edit') }}" class="block px-4 py-2 text-sm hover:bg-surface-2">Meu perfil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-surface-2">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-6">
            @include('partials.flash')
            <div class="animate-fade-up">
                @yield('content')
            </div>
        </main>

        <footer class="border-t border-line bg-surface px-6 py-4 text-xs text-muted">
            Desenvolvido por <span class="font-semibold text-body">W-Tech Solutions</span>
            · {{ config('app.name') }}
        </footer>
    </div>
</div>

<script>
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('app-sidebar')?.classList.toggle('-translate-x-full');
        });
    });
    document.querySelectorAll('[data-dropdown]').forEach(function (wrap) {
        const trigger = wrap.querySelector('[data-dropdown-trigger]');
        const menu = wrap.querySelector('[data-dropdown-menu]');
        trigger?.addEventListener('click', function (e) {
            e.stopPropagation();
            menu?.classList.toggle('hidden');
        });
        document.addEventListener('click', function () { menu?.classList.add('hidden'); });
    });
</script>
@stack('scripts')
</body>
</html>
