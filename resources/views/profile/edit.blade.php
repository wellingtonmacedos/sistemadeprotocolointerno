@extends('layouts.app')

@section('title', 'Meu perfil')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Dados cadastrais</h2>

            <form method="POST" action="{{ route('perfil.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">CPF/CNPJ</label>
                        <input name="document" value="{{ old('document', $user->document) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Telefone</label>
                        <input name="phone" value="{{ old('phone', $user->phone) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar alterações
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Alterar senha</h2>

            <form method="POST" action="{{ route('perfil.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Senha atual</label>
                    <input type="password" name="current_password" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nova senha</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar nova senha</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                    Alterar senha
                </button>
            </form>
        </div>
    </div>
@endsection
