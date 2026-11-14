@extends('layouts.app')

@section('title', 'Caixa de entrada')
@section('subtitle', 'Movimentos aguardando recebimento pelo seu setor')

@section('actions')
    <a href="{{ route('protocols.index') }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Ver protocolos
    </a>
@endsection

@section('content')
    @php
        $pendingLabels = [
            'forwardings_receipt' => 'Encaminhamentos aguardando recebimento',
            'awaiting_opinion' => 'Pareceres pendentes',
            'awaiting_analysis' => 'Proposições aguardando análise',
            'awaiting_agenda' => 'Matérias aguardando inclusão em pauta',
        ];
    @endphp

    @if (! empty($legislative))
        <div class="mb-6">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Pendências legislativas</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($legislative as $key => $group)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $pendingLabels[$key] ?? $key }}</h3>
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600">{{ $group['total'] }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @forelse ($group['items'] as $item)
                                @php
                                    $proposition = $key === 'forwardings_receipt' || $key === 'awaiting_opinion'
                                        ? $item->proposition
                                        : $item;
                                @endphp
                                <li class="py-2">
                                    @if ($proposition)
                                        <a href="{{ route('propositions.show', $proposition) }}"
                                           class="text-sm font-medium text-indigo-600 hover:underline">
                                            {{ $proposition->official_number ?? $proposition->modality->abbreviation().' '.$proposition->number.'/'.$proposition->year }}
                                        </a>
                                        @if (isset($item->commission) && $item->commission)
                                            <p class="text-xs text-slate-500">{{ $item->commission->name }}</p>
                                        @else
                                            <p class="truncate text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($proposition->title, 40) }}</p>
                                        @endif
                                    @endif
                                </li>
                            @empty
                                <li class="py-2 text-xs text-slate-400">Nenhuma pendência.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Categorias operacionais (FASE 2 — §8) --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($inboxFilters as $key => $label)
            @php $isActive = $key === $activeFilter; @endphp
            <a href="{{ route('protocols.inbox', ['filtro' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition
                      {{ $isActive ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold
                             {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $inboxCounts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Protocolo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Movimento</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Origem</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Enviado por</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prazo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ocorrência</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        @php
                            $divergence = $movement->divergences->first(fn ($item) => $item->isOpen())
                                ?? $movement->divergences->first();
                        @endphp
                        <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('protocols.show', $movement->protocol_id) }}"
                                   class="font-medium text-indigo-600 hover:underline">
                                    {{ $movement->protocol?->formatted_number }}
                                </a>
                                <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($movement->protocol?->subject, 50) }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->originLabel() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->destinationLabel() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->fromUser?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($movement->hasDeadline())
                                    <div class="flex flex-col gap-1">
                                        <span class="text-slate-700">{{ $movement->due_at->format('d/m/Y H:i') }}</span>
                                        <x-badge :color="$movement->deadlineSituation()->badge()">{{ $movement->deadlineSituation()->label() }}</x-badge>
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($movement->isRefused())
                                    <div class="flex flex-col gap-1">
                                        <x-badge color="bg-rose-100 text-rose-700">Recusado</x-badge>
                                        <span class="text-xs text-slate-500">{{ $movement->refusalLabel() }}</span>
                                    </div>
                                @elseif ($divergence)
                                    <div class="flex flex-col gap-1">
                                        <x-badge :color="$divergence->status->badge()">Divergência: {{ $divergence->status->label() }}</x-badge>
                                        <span class="text-xs text-slate-500">{{ $divergence->type->label() }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($movement->isPending())
                                    <form method="POST" action="{{ route('protocols.receive', $movement) }}" class="flex items-center justify-end gap-2">
                                        @csrf
                                        <input type="text" name="description" placeholder="Observação"
                                               class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <button type="submit"
                                                class="whitespace-nowrap rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                            Confirmar recebimento
                                        </button>
                                    </form>
                                @else
                                    <div class="text-right">
                                        <a href="{{ route('protocols.show', $movement->protocol_id) }}"
                                           class="whitespace-nowrap rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            Abrir
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-400">Nenhum registro nesta categoria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $movements->links() }}
    </div>
@endsection
