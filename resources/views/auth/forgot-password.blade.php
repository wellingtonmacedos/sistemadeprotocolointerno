@extends('layouts.guest')

@section('title', 'Recuperar senha')
@section('heading', 'Recuperar senha')
@section('description', 'Informe seu e-mail e enviaremos um link para redefinir a senha.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        @if (session('status'))
            <div class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

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

        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-card transition hover:from-brand-600 hover:to-brand-800 focus:outline-none focus:ring-4 focus:ring-brand-500/30 active:scale-[0.99]">
            Enviar link de redefinição
        </button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">Voltar para o login</a>
        </p>
    </form>
@endsection
