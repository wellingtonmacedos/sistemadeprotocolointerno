@php
    $actionLabels = [
        'proposition.created' => ['Criação', 'bg-slate-100 text-slate-700'],
        'proposition.updated' => ['Alteração', 'bg-slate-100 text-slate-700'],
        'proposition.submitted' => ['Envio para análise', 'bg-sky-100 text-sky-700'],
        'proposition.reviewed' => ['Análise', 'bg-indigo-100 text-indigo-700'],
        'proposition.registered' => ['Registro oficial', 'bg-emerald-100 text-emerald-700'],
        'proposition.deleted' => ['Exclusão', 'bg-red-100 text-red-700'],
        'proposition.forwarded' => ['Encaminhamento', 'bg-amber-100 text-amber-700'],
        'proposition.forwarding_received' => ['Recebimento', 'bg-emerald-100 text-emerald-700'],
        'proposition.forwarding_updated' => ['Situação alterada', 'bg-indigo-100 text-indigo-700'],
        'proposition.forwarding_cancelled' => ['Encaminhamento cancelado', 'bg-red-100 text-red-700'],
        'proposition.opinion_registered' => ['Parecer', 'bg-teal-100 text-teal-700'],
        'proposition.opinion_deleted' => ['Parecer removido', 'bg-red-100 text-red-700'],
        'proposition.attachment_deleted' => ['Anexo removido', 'bg-red-100 text-red-700'],
    ];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico</h3>

    @if ($timeline->isEmpty())
        <p class="py-4 text-center text-sm text-slate-400">Nenhum evento registrado.</p>
    @else
        <ol class="relative space-y-5 border-l border-slate-200 pl-6">
            @foreach ($timeline as $event)
                @php [$label, $color] = $actionLabels[$event->action] ?? [$event->action, 'bg-slate-100 text-slate-700']; @endphp
                <li class="relative">
                    <span class="absolute -left-[31px] mt-1 flex h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-white"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :color="$color">{{ $label }}</x-badge>
                        <span class="text-xs text-slate-400">
                            {{ $event->created_at->format('d/m/Y H:i') }}
                            @if ($event->user) · {{ $event->user->name }} @endif
                        </span>
                    </div>
                    @if ($event->description)
                        <p class="mt-1 text-sm text-slate-700">{{ $event->description }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
