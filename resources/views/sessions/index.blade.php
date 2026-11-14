@extends('layouts.app')

@section('title', 'Sessões')
@section('subtitle', 'Sessões legislativas da Câmara')

@section('actions')
    @if (auth()->user()->hasPermission('sessions.manage'))
        <a href="{{ route('sessions.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Nova sessão
        </a>
    @endif
@endsection

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Número ou local"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
            <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Situação</label>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Filtrar
        </button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nº</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data / Hora</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Local</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Presidente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pauta</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($sessions as $session)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $session->number }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $session->type->label() }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $session->date->format('d/m/Y') }}
                        @if ($session->time) · {{ $session->time }} @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $session->location ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $session->president?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $session->agenda_items_count }} item(ns)</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$session->status->badge()">{{ $session->status->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('sessions.show', $session) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Ver
                            </a>
                            @if (auth()->user()->hasPermission('sessions.manage'))
                                <a href="{{ route('sessions.edit', $session) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma sessão cadastrada.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
@endsection
