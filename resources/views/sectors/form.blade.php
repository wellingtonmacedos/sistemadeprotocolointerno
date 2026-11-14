@extends('layouts.app')
@php $sector = $sector ?? null; @endphp

@section('title', $sector ? 'Editar setor' : 'Novo setor')
@section('subtitle', $sector ? 'Atualize os dados do setor' : 'Cadastre um novo setor')

@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST"
              action="{{ $sector ? route('sectors.update', $sector) : route('sectors.store') }}"
              class="space-y-4">
            @csrf
            @if ($sector)
                @method('PUT')
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $sector->name ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sigla</label>
                    <input name="acronym" value="{{ old('acronym', $sector->acronym ?? '') }}" maxlength="30"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('acronym') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ordem de exibição</label>
                <input type="number" name="display_order" min="0"
                       value="{{ old('display_order', $sector->display_order ?? 0) }}"
                       class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('display_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                <textarea name="description" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $sector->description ?? '') }}</textarea>
                @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Responsável</label>
                <select name="responsible_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Sem responsável —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}"
                            @selected((int) old('responsible_user_id', $sector->responsible_user_id ?? 0) === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('responsible_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Câmara</label>
                    <select name="institution_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}"
                                @selected((int) old('institution_id', $sector->institution_id ?? 0) === $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Recursos para a tramitação legislativa
                </legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="can_receive" value="0">
                        <input type="checkbox" name="can_receive" value="1"
                               @checked((bool) old('can_receive', $sector->can_receive ?? true))
                               class="h-4 w-4 rounded border-slate-300">
                        Pode receber encaminhamentos
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="can_dispatch" value="0">
                        <input type="checkbox" name="can_dispatch" value="1"
                               @checked((bool) old('can_dispatch', $sector->can_dispatch ?? true))
                               class="h-4 w-4 rounded border-slate-300">
                        Pode emitir despachos
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="can_attach" value="0">
                        <input type="checkbox" name="can_attach" value="1"
                               @checked((bool) old('can_attach', $sector->can_attach ?? true))
                               class="h-4 w-4 rounded border-slate-300">
                        Pode anexar documentos
                    </label>
                </div>
            </fieldset>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       @checked((bool) old('is_active', $sector->is_active ?? true))
                       class="h-4 w-4 rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Setor ativo</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar
                </button>
                <a href="{{ route('sectors.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
