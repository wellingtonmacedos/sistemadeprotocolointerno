@php
    /** @var \App\Models\Protocol $protocol */
@endphp

@forelse ($movements as $movement)
    @php
        $dotColor = $movement->isRefused() ? 'bg-rose-500' : ($movement->isPending() ? 'bg-amber-500' : 'bg-emerald-500');
        $badgeColor = $movement->isRefused() ? 'bg-rose-100 text-rose-700' : ($movement->isPending() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
    @endphp
    <div class="relative border-l-2 border-slate-100 pb-6 pl-6 last:pb-0 last:border-transparent">
        <span class="absolute -left-[7px] top-1 h-3 w-3 rounded-full {{ $dotColor }}"></span>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-slate-800">{{ $movement->type->label() }}</span>
            <x-badge :color="$badgeColor">
                {{ $movement->status->label() }}
            </x-badge>
        </div>

        <p class="mt-1 text-xs text-slate-500">
            {{ $movement->originLabel() }}
            <span class="mx-1 text-slate-300">→</span>
            {{ $movement->destinationLabel() }}
        </p>

        <p class="mt-1 text-xs text-slate-400">
            {{ $movement->fromUser?->name ?? 'Sistema' }}
            · {{ $movement->created_at?->format('d/m/Y H:i') }}
            @if ($movement->received_at)
                · recebido em {{ $movement->received_at->format('d/m/Y H:i') }}
                @if ($movement->receiver) por {{ $movement->receiver->name }} @endif
            @endif
        </p>

        @if ($movement->hasPermanence())
            <p class="mt-1 text-xs text-slate-500">
                Permanência no destino: {{ $movement->permanenceStartedAt()->format('d/m/Y H:i') }}
                <span class="mx-1 text-slate-300">→</span>
                {{ $movement->permanenceEndedAt()->format('d/m/Y H:i') }}
                <span class="text-slate-400">({{ $movement->permanenceDuration() }})</span>
            </p>
        @endif

        @if ($movement->hasDeadline())
            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>Prazo: {{ $movement->deadlineLabel() }}</span>
                <span>· Data-limite: {{ $movement->due_at->format('d/m/Y H:i') }}</span>
                <x-badge :color="$movement->deadlineSituation()->badge()">{{ $movement->deadlineSituation()->label() }}</x-badge>
            </p>
        @endif

        @if ($movement->isRefused())
            <div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                <p class="font-medium">Recebimento recusado: {{ $movement->refusalLabel() }}</p>
                <p class="mt-0.5 text-xs">
                    {{ $movement->refused_at?->format('d/m/Y H:i') }}
                    @if ($movement->refusedBy) · por {{ $movement->refusedBy->name }} @endif
                </p>
                @if ($movement->refusal_notes)
                    <p class="mt-1 whitespace-pre-line">{{ $movement->refusal_notes }}</p>
                @endif
            </div>
        @endif

        @if ($movement->description)
            <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $movement->description }}</p>
        @endif

        @if ($movement->attachments->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach ($movement->attachments as $attachment)
                    <a href="{{ route('protocols.attachments.download', $attachment) }}"
                       class="text-xs font-medium text-indigo-600 hover:underline">
                        {{ $attachment->original_name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">Nenhuma tramitação registrada.</p>
@endforelse
