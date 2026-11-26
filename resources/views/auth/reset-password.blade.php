@extends('layouts.guest')

@section('title', 'Definir nova senha')
@section('heading', 'Definir nova senha')
@section('description', 'Escolha uma nova senha para a sua conta.')

@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

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
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required
                   class="w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-body placeholder:text-faint focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-body">Nova senha</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                   placeholder="••••••••"
                   class="w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-body placeholder:text-faint focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-body">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   placeholder="••••••••"
                   class="w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-body placeholder:text-faint focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15">
        </div>

        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-card transition hover:from-brand-600 hover:to-brand-800 focus:outline-none focus:ring-4 focus:ring-brand-500/30 active:scale-[0.99]">
            Redefinir senha
        </button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">Voltar para o login</a>
        </p>
    </form>
@endsection
