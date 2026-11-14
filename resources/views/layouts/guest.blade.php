<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acesso') · {{ config('app.name') }}</title>
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
<div class="grid min-h-screen lg:grid-cols-[1.1fr_minmax(0,_1fr)]">

    {{-- Painel de destaque (oculto no mobile) --}}
    <aside class="relative hidden overflow-hidden bg-gradient-to-br from-ink-950 via-brand-900 to-brand-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-accent-400/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 -left-16 h-96 w-96 rounded-full bg-brand-400/20 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 text-lg font-bold ring-1 ring-white/20 backdrop-blur">
                P
            </span>
            <span class="text-sm font-semibold uppercase tracking-[0.18em] text-white/80">
                {{ config('app.name') }}
            </span>
        </div>

        <div class="relative max-w-md animate-fade-up">
            <h2 class="text-3xl font-semibold leading-tight">
                Protocolo e tramitação legislativa, com clareza e controle.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-white/70">
                Centralize protocolos, proposições, comissões e sessões em um único ambiente,
                com rastreabilidade completa e isolamento entre instituições.
            </p>

            <ul class="mt-8 space-y-3 text-sm text-white/80">
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    Tramitação documental com prazos e responsáveis
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    Gestão de proposições, comissões e sessões
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    Auditoria completa das ações no sistema
                </li>
            </ul>
        </div>

        <p class="relative text-xs text-white/50">
            Desenvolvido por <span class="font-semibold text-white/80">W-Tech Solutions</span>
        </p>
    </aside>

    {{-- Área de acesso --}}
    <main class="relative flex items-center justify-center p-6">
        <button type="button" data-theme-toggle
                class="absolute right-6 top-6 rounded-lg border border-line bg-surface p-2 text-muted shadow-card hover:text-body"
                aria-label="Alternar tema" aria-pressed="false">
            <svg data-theme-icon="moon" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
            <svg data-theme-icon="sun" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M5.64 5.64l1.42 1.42m9.88 9.88l1.42 1.42M3 12h2m14 0h2M5.64 18.36l1.42-1.42m9.88-9.88l1.42-1.42M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
        </button>

        <div class="w-full max-w-sm animate-fade-up">
            <div class="mb-8 text-center lg:hidden">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-2xl font-bold text-white shadow-elevated">
                    P
                </div>
                <h1 class="text-lg font-semibold text-body">{{ config('app.name') }}</h1>
                <p class="mt-1 text-sm text-muted">Gestão de Protocolos e Proposições</p>
            </div>

            @hasSection('heading')
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold tracking-tight text-body">@yield('heading')</h1>
                    @hasSection('description')
                        <p class="mt-1 text-sm text-muted">@yield('description')</p>
                    @endif
                </div>
            @endif

            <div class="rounded-2xl border border-line bg-surface p-8 shadow-elevated">
                @yield('content')
            </div>

            <p class="mt-6 text-center text-xs text-faint lg:hidden">
                Desenvolvido por <span class="font-semibold text-muted">W-Tech Solutions</span>
            </p>
        </div>
    </main>
</div>
</body>
</html>
