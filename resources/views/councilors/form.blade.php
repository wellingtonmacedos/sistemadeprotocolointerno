@extends('layouts.app')
@php $councilor = $councilor ?? null; @endphp

@section('title', $councilor ? 'Editar vereador' : 'Novo vereador')
@section('subtitle', $councilor ? 'Atualize os dados do vereador' : 'Cadastre um novo vereador')

@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST"
              action="{{ $councilor ? route('councilors.update', $councilor) : route('councilors.store') }}"
              class="space-y-4">
            @csrf
            @if ($councilor)
                @method('PUT')
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                <input name="name" value="{{ old('name', $councilor->name ?? '') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Partido</label>
                    <input name="party" value="{{ old('party', $councilor->party ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('party') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Telefone</label>
                    <input name="phone" value="{{ old('phone', $councilor->phone ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $councilor->email ?? '') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mandato</label>
                    <input name="mandate" value="{{ old('mandate', $councilor->mandate ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('mandate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Legislatura</label>
                    <input name="legislature" value="{{ old('legislature', $councilor->legislature ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('legislature') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Usuário vinculado</label>
                <select name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Sem vínculo —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}"
                            @selected((int) old('user_id', $councilor->user_id ?? 0) === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Câmara</label>
                    <select name="institution_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}"
                                @selected((int) old('institution_id', $councilor->institution_id ?? 0) === $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       @checked((bool) old('is_active', $councilor->is_active ?? true))
                       class="h-4 w-4 rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Vereador ativo</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar
                </button>
                <a href="{{ route('councilors.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
