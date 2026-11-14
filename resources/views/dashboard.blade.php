@extends('layouts.app')

@section('title', 'Painel')
@section('subtitle', 'Visão geral dos protocolos e proposições legislativas')

@section('actions')
    @if (auth()->user()->hasPermission('protocols.create'))
        <a href="{{ route('protocols.create') }}"
           class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo protocolo
        </a>
    @endif
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total de protocolos" :value="$stats['protocols_total']" accent="indigo" />
        <x-stat-card label="Em tramitação" :value="$stats['protocols_in_progress']" accent="sky" />
        <x-stat-card label="Aguardando recebimento" :value="$stats['protocols_pending']" accent="amber" />
        <x-stat-card label="Protocolos concluídos" :value="$stats['protocols_done']" accent="emerald" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Proposições em análise" :value="$stats['propositions_in_review']" accent="indigo" />
        <x-stat-card label="Proposições aprovadas" :value="$stats['propositions_approved']" accent="emerald" />
        <x-stat-card label="Proposições registradas" :value="$stats['propositions_registered']" accent="sky" />
        <x-stat-card label="Vereadores ativos" :value="$stats['councilors_total']" accent="slate" />
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Indicadores legislativos</h2>
            @if (auth()->user()->hasPermission('reports.view'))
                <a href="{{ route('reports.propositions') }}" class="text-xs font-medium text-indigo-600 hover:underline">Relatório detalhado</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                ['Protocoladas', $legislative['protocoled'], 'indigo'],
                ['Em análise', $legislative['in_analysis'], 'indigo'],
                ['Aguardando parecer', $legislative['awaiting_opinion'], 'sky'],
                ['Em comissões', $legislative['in_commissions'], 'sky'],
                ['Aguardando pauta', $legislative['awaiting_agenda'], 'amber'],
                ['Pautadas', $legislative['scheduled'], 'amber'],
                ['Aprovadas', $legislative['approved'], 'emerald'],
                ['Rejeitadas', $legislative['rejected'], 'red'],
                ['Aguardando sanção', $legislative['awaiting_sanction'], 'amber'],
                ['Promulgadas', $legislative['promulgated'], 'emerald'],
                ['Publicadas', $legislative['published'], 'emerald'],
                ['Concluídas', $legislative['concluded'], 'slate'],
            ] as [$label, $value, $accent])
                <x-stat-card :label="$label" :value="$value" :accent="$accent" />
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Movimentações recentes</h2>

            @forelse ($recentMovements as $movement)
                <div class="flex gap-3 border-b border-slate-100 py-3 last:border-0">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-700">
                            <span class="font-medium">{{ $movement->type->label() }}</span>
                            · <a class="text-indigo-600 hover:underline"
                                 href="{{ route('protocols.show', $movement->protocol_id) }}">
                                {{ $movement->protocol?->formatted_number }}
                            </a>
                            @if ($movement->toSector)
                                → {{ $movement->toSector->name }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $movement->fromUser?->name ?? 'Sistema' }} · {{ $movement->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <x-badge :color="$movement->status->value === 'recebido' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                        {{ $movement->status->label() }}
                    </x-badge>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Nenhuma movimentação registrada.</p>
            @endforelse
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Protocolos por situação</h2>

            @php
                $max = max(1, (int) $byStatus->max());
            @endphp

            @forelse ($byStatus as $status => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                        <span>{{ \App\Enums\ProtocolStatus::tryFrom($status)?->label() ?? $status }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ ($total / $max) * 100 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Sem dados.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Últimos protocolos</h2>
                <a href="{{ route('protocols.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">Ver todos</a>
            </div>
            <table class="w-full text-sm">
                <tbody>
                @forelse ($recentProtocols as $protocol)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-medium text-indigo-600">{{ $protocol->formatted_number }}</td>
                        <td class="px-2 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($protocol->subject, 40) }}</td>
                        <td class="px-5 py-3 text-right">
                            <x-badge :color="$protocol->status->badge()">{{ $protocol->status->label() }}</x-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-center text-slate-400" colspan="3">Nenhum protocolo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Últimas proposições</h2>
                @if (auth()->user()->hasPermission('propositions.view'))
                    <a href="{{ route('propositions.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">Ver todas</a>
                @endif
            </div>
            <table class="w-full text-sm">
                <tbody>
                @forelse ($recentPropositions as $proposition)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-medium text-slate-700">
                            {{ $proposition->official_number ?? $proposition->modality->abbreviation() }}
                        </td>
                        <td class="px-2 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($proposition->title, 40) }}</td>
                        <td class="px-5 py-3 text-right">
                            <x-badge :color="$proposition->status->badge()">{{ $proposition->status->label() }}</x-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-center text-slate-400" colspan="3">Nenhuma proposição.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
