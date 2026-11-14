@extends('layouts.app')

@section('title', $session->exists ? 'Editar sessão' : 'Nova sessão')
@section('subtitle', 'Sessões legislativas')

@section('actions')
    <a href="{{ route('sessions.index') }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ $session->exists ? route('sessions.update', $session) : route('sessions.store') }}"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($session->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Número da sessão</label>
                <input type="number" name="number" min="1" value="{{ old('number', $session->number) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                <select name="type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $session->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Data</label>
                <input type="date" name="date" value="{{ old('date', $session->date?->format('Y-m-d')) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Horário</label>
                <input type="time" name="time" value="{{ old('time', $session->time) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Local</label>
                <input type="text" name="location" value="{{ old('location', $session->location) }}" maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('location') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Presidente da sessão</label>
                <select name="president_councilor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Não informado —</option>
                    @foreach ($councilors as $councilor)
                        <option value="{{ $councilor->id }}"
                            @selected((int) old('president_councilor_id', $session->president_councilor_id) === $councilor->id)>
                            {{ $councilor->name }}
                        </option>
                    @endforeach
                </select>
                @error('president_councilor_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Situação</label>
                <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $session->status?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                <textarea name="notes" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $session->notes) }}</textarea>
                @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $session->exists ? 'Salvar alterações' : 'Cadastrar sessão' }}
            </button>
        </div>
    </form>
@endsection
