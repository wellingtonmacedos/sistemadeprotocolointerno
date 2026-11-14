@extends('layouts.app')
@php $user = $user ?? null; @endphp

@section('title', $user ? 'Editar usuário' : 'Novo usuário')
@section('subtitle', $user ? 'Atualize os dados do usuário' : 'Cadastre um novo usuário')

@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST"
              action="{{ $user ? route('users.update', $user) : route('users.store') }}"
              class="space-y-4">
            @csrf
            @if ($user)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $user->name ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Telefone</label>
                    <input name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">CPF/CNPJ</label>
                    <input name="document" value="{{ old('document', $user->document ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('document') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Perfil</label>
                    <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}"
                                @selected((int) old('role_id', $user->role_id ?? 0) === $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Setor</label>
                    <select name="sector_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Sem setor —</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->id }}"
                                @selected((int) old('sector_id', $user->sector_id ?? 0) === $sector->id)>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sector_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Vereador vinculado</label>
                    <select name="councilor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Sem vínculo —</option>
                        @foreach ($councilors as $councilor)
                            <option value="{{ $councilor->id }}"
                                @selected((int) old('councilor_id', $user->councilor_id ?? 0) === $councilor->id)>
                                {{ $councilor->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('councilor_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if (auth()->user()->isSuperAdmin())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Câmara</label>
                        <select name="institution_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— Selecione —</option>
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution->id }}"
                                    @selected((int) old('institution_id', $user->institution_id ?? 0) === $institution->id)>
                                    {{ $institution->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('institution_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Senha @if ($user) <span class="text-slate-400">(deixe em branco para manter)</span> @endif
                    </label>
                    <input type="password" name="password" @if (! $user) required @endif
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar senha</label>
                    <input type="password" name="password_confirmation" @if (! $user) required @endif
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       @checked((bool) old('is_active', $user->is_active ?? true))
                       class="h-4 w-4 rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Usuário ativo</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar
                </button>
                <a href="{{ route('users.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
