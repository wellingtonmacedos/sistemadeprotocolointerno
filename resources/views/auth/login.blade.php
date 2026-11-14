@extends('layouts.guest')

@section('title', 'Entrar')
@section('heading', 'Acessar o sistema')
@section('description', 'Informe suas credenciais para continuar.')

@section('content')
    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
        @csrf

        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-body">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   placeholder="voce@instituicao.gov.br"
                   class="w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-body placeholder:text-faint focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-body">Senha</label>
            <input id="password" name="password" type="password" required
                   placeholder="••••••••"
                   class="w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-body placeholder:text-faint focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15">
        </div>

        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" value="1"
                   class="h-4 w-4 rounded border-line-strong text-brand-600 focus:ring-brand-500/30">
            Lembrar-me neste dispositivo
        </label>

        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-card transition hover:from-brand-600 hover:to-brand-800 focus:outline-none focus:ring-4 focus:ring-brand-500/30 active:scale-[0.99]">
            Entrar
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
        </button>
    </form>
@endsection
