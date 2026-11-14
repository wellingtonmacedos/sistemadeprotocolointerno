@extends('layouts.app')

@section('title', 'Fluxos Legislativos')
@section('subtitle', 'Configure o fluxo de tramitação por modalidade de proposição')

@section('actions')
    @if (auth()->user()->hasPermission('legislative_flows.manage'))
        <a href="{{ route('legislative-flows.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo fluxo
        </a>
    @endif
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Modalidade</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fluxo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Versão vigente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Etapas</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($flows as $flow)
                @php $current = $flow->versions->firstWhere('is_current', true); @endphp
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3">
                        <x-badge color="bg-indigo-50 text-indigo-700">{{ $flow->modalityLabel() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $flow->name }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        @if ($current)
                            v{{ $current->version }}
                            <span class="text-xs text-slate-500">· {{ $current->published_at?->format('d/m/Y') }}</span>
                        @else
                            <span class="text-slate-400">Não publicado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $current?->steps_count ?? 0 }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('legislative-flows.show', $flow) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Gerenciar
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum fluxo configurado.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $flows->links() }}</div>
@endsection
