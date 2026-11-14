@extends('layouts.app')
@php
    $commission = $commission ?? null;
    $selectedModalities = old('allowed_modalities', $commission?->allowed_modalities ?? []);
@endphp

@section('title', $commission ? 'Editar comissão' : 'Nova comissão')
@section('subtitle', $commission ? 'Atualize os dados da comissão' : 'Cadastre uma nova comissão legislativa')

@section('content')
    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST"
              action="{{ $commission ? route('commissions.update', $commission) : route('commissions.store') }}"
              class="space-y-4">
            @csrf
            @if ($commission)
                @method('PUT')
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $commission->name ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sigla</label>
                    <input name="acronym" value="{{ old('acronym', $commission->acronym ?? '') }}" required maxlength="30"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('acronym') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                    <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (\App\Enums\CommissionType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $commission->type ?? 'permanente') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ordem de exibição</label>
                    <input type="number" name="display_order" min="0"
                           value="{{ old('display_order', $commission->display_order ?? 0) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('display_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               @checked((bool) old('is_active', $commission->is_active ?? true))
                               class="h-4 w-4 rounded border-slate-300">
                        Comissão ativa
                    </label>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                <input name="description" value="{{ old('description', $commission->description ?? '') }}" maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Competências</label>
                <textarea name="competences" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('competences', $commission->competences ?? '') }}</textarea>
                @error('competences') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Tipos de proposição que pode analisar</label>
                <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                    @foreach (\App\Enums\PropositionModality::options() as $value => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="allowed_modalities[]" value="{{ $value }}"
                                   @checked(in_array($value, (array) $selectedModalities, true))
                                   class="h-4 w-4 rounded border-slate-300">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('allowed_modalities') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Câmara</label>
                    <select name="institution_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}"
                                @selected((int) old('institution_id', $commission->institution_id ?? 0) === $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar
                </button>
                <a href="{{ route('commissions.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
