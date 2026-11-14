@extends('layouts.app')

@section('title', 'Minhas proposições')
@section('subtitle', 'Proposições de sua autoria ou coautoria')

@section('actions')
    @if (auth()->user()->hasPermission('propositions.create'))
        <a href="{{ route('propositions.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Nova proposição
        </a>
    @endif
@endsection

@section('content')
    <form method="GET" action="{{ route('councilor.propositions') }}"
          class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Situação</label>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Filtrar
        </button>
        <a href="{{ route('councilor.propositions') }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Limpar
        </a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Numeração</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Modalidade</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Título</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($propositions as $proposition)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $proposition->official_number ?? $proposition->modality->abbreviation() }}
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $proposition->modality->label() }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('propositions.show', $proposition) }}" class="font-medium text-indigo-600 hover:underline">
                            {{ \Illuminate\Support\Str::limit($proposition->title, 70) }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :color="$proposition->status->badge()">{{ $proposition->status->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $proposition->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">Nenhuma proposição encontrada.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $propositions->links() }}
    </div>
@endsection
