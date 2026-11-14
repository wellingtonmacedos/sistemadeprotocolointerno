@extends('layouts.app')

@section('title', 'Painel do Vereador')
@section('subtitle', 'Acompanhe suas proposições legislativas')

@section('actions')
    @if (auth()->user()->hasPermission('propositions.create'))
        <a href="{{ route('propositions.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Nova proposição
        </a>
    @endif
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Total de proposições" :value="$stats['total']" accent="indigo" />
        <x-stat-card label="Rascunhos" :value="$stats['rascunhos']" accent="slate" />
        <x-stat-card label="Em análise" :value="$stats['em_analise']" accent="sky" />
        <x-stat-card label="Aprovadas" :value="$stats['aprovadas']" accent="emerald" />
        <x-stat-card label="Registradas" :value="$stats['registradas']" accent="amber" />
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Minhas proposições recentes</h2>
            <a href="{{ route('councilor.propositions') }}" class="text-xs font-medium text-indigo-600 hover:underline">
                Ver todas
            </a>
        </div>

        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
            @forelse ($recent as $proposition)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-700">
                        {{ $proposition->official_number ?? $proposition->modality->abbreviation() }}
                    </td>
                    <td class="px-2 py-3">
                        <a href="{{ route('propositions.show', $proposition) }}" class="text-indigo-600 hover:underline">
                            {{ \Illuminate\Support\Str::limit($proposition->title, 60) }}
                        </a>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <x-badge :color="$proposition->status->badge()">{{ $proposition->status->label() }}</x-badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-5 py-8 text-center text-sm text-slate-400">
                        Você ainda não possui proposições.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
