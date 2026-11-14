@extends('layouts.app')

@section('title', 'Relatórios')
@section('subtitle', 'Indicadores consolidados de protocolos e proposições')

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <select name="year" onchange="this.form.submit()"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected($option == $year)>{{ $option }}</option>
            @endforeach
        </select>
    </form>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Protocolos no exercício" :value="$totals['protocols']" accent="indigo" />
        <x-stat-card label="Protocolos concluídos" :value="$totals['concluded']" accent="emerald" />
        <x-stat-card label="Proposições no exercício" :value="$totals['propositions']" accent="sky" />
        <x-stat-card label="Proposições registradas" :value="$totals['registered']" accent="amber" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('reports.protocols') }}"
           class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
            <p class="text-sm font-semibold text-slate-800">Relatório de Protocolos</p>
            <p class="mt-1 text-xs text-slate-500">Filtre por período, situação, tipo de documento e setor.</p>
        </a>
        <a href="{{ route('reports.propositions') }}"
           class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
            <p class="text-sm font-semibold text-slate-800">Relatório de Proposições</p>
            <p class="mt-1 text-xs text-slate-500">Filtre por período, modalidade e situação.</p>
        </a>
        @if (auth()->user()->hasPermission('audit.view'))
            <a href="{{ route('audit.index') }}"
               class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
                <p class="text-sm font-semibold text-slate-800">Trilha de Auditoria</p>
                <p class="mt-1 text-xs text-slate-500">Consulte as operações sensíveis registradas.</p>
            </a>
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        @php $maxStatus = max(1, (int) $protocolsByStatus->max()); @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Protocolos por situação ({{ $year }})</h2>
            @forelse ($protocolsByStatus as $status => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                        <span>{{ \App\Enums\ProtocolStatus::tryFrom($status)?->label() ?? $status }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ ($total / $maxStatus) * 100 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Sem dados.</p>
            @endforelse
        </div>

        @php $maxType = max(1, (int) $protocolsByType->max()); @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Protocolos por tipo de documento ({{ $year }})</h2>
            @forelse ($protocolsByType as $type => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                        <span>{{ \App\Enums\DocumentType::tryFrom($type)?->label() ?? $type }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-sky-500" style="width: {{ ($total / $maxType) * 100 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Sem dados.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        @php $maxSector = max(1, (int) $protocolsBySector->max()); @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Protocolos por setor de destino ({{ $year }})</h2>
            @forelse ($protocolsBySector as $sectorId => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                        <span>{{ $sectors[$sectorId] ?? 'Setor removido' }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ ($total / $maxSector) * 100 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Sem dados.</p>
            @endforelse
        </div>

        @php $maxModality = max(1, (int) $propositionsByModality->max()); @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Proposições por modalidade ({{ $year }})</h2>
            @forelse ($propositionsByModality as $modality => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                        <span>{{ \App\Enums\PropositionModality::tryFrom($modality)?->label() ?? $modality }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-amber-500" style="width: {{ ($total / $maxModality) * 100 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">Sem dados.</p>
            @endforelse
        </div>
    </div>
@endsection
