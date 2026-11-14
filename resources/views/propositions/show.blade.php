@extends('layouts.app')

@section('title', $proposition->official_number ?? $proposition->modality->label())
@section('subtitle', $proposition->title)

@php
    $user = auth()->user();
    $editable = in_array($proposition->status, [
        \App\Enums\PropositionStatus::RASCUNHO,
        \App\Enums\PropositionStatus::DEVOLVIDA,
    ], true);
    $canCreate = $user->hasPermission('propositions.create');
    $canReview = $user->hasPermission('propositions.review');
    $canRegister = $user->hasPermission('propositions.register');
    $canForward = $user->hasPermission('propositions.forward');
    $canPostVoting = $user->hasPermission('votings.post_actions');

    $coauthors = $proposition->authors->where('pivot.is_primary', false);

    $flow = [
        \App\Enums\PropositionStatus::RASCUNHO,
        \App\Enums\PropositionStatus::ENVIADA,
        \App\Enums\PropositionStatus::EM_ANALISE,
        \App\Enums\PropositionStatus::APROVADA,
        \App\Enums\PropositionStatus::REGISTRADA,
    ];

    $progress = match ($proposition->status) {
        \App\Enums\PropositionStatus::RASCUNHO => 0,
        \App\Enums\PropositionStatus::ENVIADA,
        \App\Enums\PropositionStatus::REENVIADA => 1,
        \App\Enums\PropositionStatus::EM_ANALISE,
        \App\Enums\PropositionStatus::DEVOLVIDA => 2,
        \App\Enums\PropositionStatus::APROVADA => 3,
        \App\Enums\PropositionStatus::REGISTRADA => 4,
        default => 0,
    };

    $openForwarding = $proposition->forwardings->first(fn ($f) => $f->isOpen());

    $tabs = [
        'dados' => 'Dados gerais',
        'tramitacao' => 'Tramitação',
        'comissoes' => 'Comissões e pareceres',
        'sessoes' => 'Sessões e pautas',
        'votacoes' => 'Votações',
        'documentos' => 'Documentos',
        'historico' => 'Histórico',
    ];

    $activeTab = $errors->any() ? 'comissoes' : 'dados';
@endphp

@section('actions')
    @if ($editable && $canCreate)
        <a href="{{ route('propositions.edit', $proposition) }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Editar
        </a>
    @endif
    <a href="{{ route('propositions.document', $proposition) }}"
       class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
        Documento PDF
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Resumo persistente --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                            {{ $proposition->official_number ?? $proposition->modality->abbreviation() }}
                        </p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-800">{{ $proposition->title }}</h2>
                    </div>
                    <x-badge :color="$proposition->status->badge()">{{ $proposition->status->label() }}</x-badge>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Modalidade</dt>
                        <dd class="text-sm text-slate-700">{{ $proposition->modality->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Origem</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $proposition->origin->badge() }}">
                                {{ $proposition->origin->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Autor</dt>
                        <dd class="text-sm text-slate-700">{{ $proposition->authorName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Coautores</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $coauthors->isNotEmpty() ? $coauthors->pluck('name')->join(', ') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Data de apresentação</dt>
                        <dd class="text-sm text-slate-700">
                            {{ ($proposition->submitted_at ?? $proposition->created_at)->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Etapa atual</dt>
                        <dd class="text-sm text-slate-700">{{ $proposition->status->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Comissão responsável</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $openForwarding?->commission?->name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Prazo</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $openForwarding?->deadline?->format('d/m/Y') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Abas --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" id="proposition-tabs">
                <div class="flex flex-wrap gap-1 border-b border-slate-200 px-2 pt-2">
                    @foreach ($tabs as $key => $label)
                        <button type="button"
                                data-tab="{{ $key }}"
                                class="rounded-t-lg px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-800">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="p-6">
                    {{-- Dados gerais --}}
                    <div data-panel="dados" class="space-y-5 text-sm text-slate-700">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ementa</p>
                            <p class="mt-1 whitespace-pre-line">{{ $proposition->ementa }}</p>
                        </div>
                        @if ($proposition->text)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Texto</p>
                                <p class="mt-1 whitespace-pre-line">{{ $proposition->text }}</p>
                            </div>
                        @endif
                        @if ($proposition->justification)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Justificativa</p>
                                <p class="mt-1 whitespace-pre-line">{{ $proposition->justification }}</p>
                            </div>
                        @endif
                        @if ($proposition->observations)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Observações</p>
                                <p class="mt-1 whitespace-pre-line">{{ $proposition->observations }}</p>
                            </div>
                        @endif

                        @if ($proposition->official_number)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Registro oficial</p>
                                <p class="mt-1 text-sm font-semibold text-emerald-800">{{ $proposition->official_number }}</p>
                                @if ($proposition->registered_at)
                                    <p class="mt-1 text-xs text-emerald-600">
                                        Registrada em {{ $proposition->registered_at->format('d/m/Y H:i') }}
                                        @if ($proposition->registrar) por {{ $proposition->registrar->name }} @endif
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Tramitação --}}
                    <div data-panel="tramitacao" class="space-y-5">
                        <div>
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Andamento</h3>
                            <ol class="flex flex-wrap items-center gap-2">
                                @foreach ($flow as $index => $step)
                                    @php $done = $index <= $progress; @endphp
                                    <li class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold
                                            {{ $done ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="text-xs font-medium {{ $done ? 'text-slate-700' : 'text-slate-400' }}">
                                            {{ $step->label() }}
                                        </span>
                                        @unless ($loop->last)
                                            <span class="mx-1 h-px w-6 bg-slate-200"></span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        @if ($proposition->review_notes)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Observações da análise</p>
                                <p class="mt-1 whitespace-pre-line">{{ $proposition->review_notes }}</p>
                            </div>
                        @endif

                        @if ($proposition->forwardings->isNotEmpty())
                            <div>
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Encaminhamentos</h4>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($proposition->forwardings as $forwarding)
                                        <li class="flex items-center justify-between gap-3 py-2">
                                            <span class="text-sm text-slate-700">{{ $forwarding->commission?->name }}</span>
                                            <x-badge :color="$forwarding->status->badge()">{{ $forwarding->status->label() }}</x-badge>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    {{-- Comissões e pareceres --}}
                    <div data-panel="comissoes" class="space-y-6">
                        @include('propositions.partials.forwardings', [
                            'proposition' => $proposition,
                            'commissions' => $commissions,
                            'councilors' => $councilors,
                            'forwardingStatuses' => $forwardingStatuses,
                            'opinionResults' => $opinionResults,
                        ])
                    </div>

                    {{-- Sessões e pautas --}}
                    <div data-panel="sessoes">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Sessões em que a matéria foi pautada</h3>

                        @if ($proposition->agendaItems->isEmpty())
                            <p class="py-6 text-center text-sm text-slate-400">
                                Esta proposição ainda não consta em nenhuma pauta de sessão.
                            </p>
                        @else
                            <div class="overflow-hidden rounded-xl border border-slate-200">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sessão</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Apreciação</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação na pauta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($proposition->agendaItems as $item)
                                            <tr class="border-b border-slate-50 last:border-0">
                                                <td class="px-4 py-2 text-slate-700">
                                                    @if ($item->session)
                                                        <a href="{{ route('sessions.show', $item->session) }}"
                                                           class="font-medium text-indigo-600 hover:underline">
                                                            {{ $item->session->type?->label() }} nº {{ $item->session->number }}
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-slate-700">{{ $item->session?->date?->format('d/m/Y') ?? '—' }}</td>
                                                <td class="px-4 py-2 text-slate-700">{{ $item->position }}º</td>
                                                <td class="px-4 py-2 text-slate-700">{{ $item->appreciation_type->label() }}</td>
                                                <td class="px-4 py-2">
                                                    <x-badge :color="$item->status->badge()">{{ $item->status->label() }}</x-badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-3 text-xs text-slate-400">
                                A inclusão na pauta não representa aprovação: a deliberação é ato do Plenário.
                            </p>
                        @endif
                    </div>

                    {{-- Votações --}}
                    <div data-panel="votacoes" class="space-y-6">
                        <div>
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Votações em Plenário</h3>

                            @if ($proposition->votings->isEmpty())
                                <p class="py-6 text-center text-sm text-slate-400">
                                    Nenhuma votação registrada para esta proposição.
                                </p>
                            @else
                                <div class="space-y-4">
                                    @foreach ($proposition->votings as $voting)
                                        <div class="rounded-xl border border-slate-200 p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-800">
                                                        {{ $voting->type->label() }} · {{ $voting->turno }}º turno
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-400">
                                                        @if ($voting->session)
                                                            <a href="{{ route('sessions.show', $voting->session) }}"
                                                               class="font-medium text-indigo-600 hover:underline">
                                                                Sessão {{ $voting->session->type?->label() }} nº {{ $voting->session->number }}
                                                            </a>
                                                            · {{ $voting->session->date?->format('d/m/Y') }}
                                                        @else
                                                            Sessão não vinculada
                                                        @endif
                                                        @if ($voting->quorum_rule) · Quórum: {{ $voting->quorum_rule->label() }} @endif
                                                    </p>
                                                </div>
                                                @if ($voting->result)
                                                    <x-badge :color="$voting->result->badge()">{{ $voting->result->label() }}</x-badge>
                                                @else
                                                    <x-badge color="bg-amber-100 text-amber-700">Em apuração</x-badge>
                                                @endif
                                            </div>

                                            <dl class="mt-3 grid grid-cols-2 gap-2 text-center sm:grid-cols-5">
                                                <div class="rounded-lg bg-emerald-50 py-2">
                                                    <dt class="text-xs text-emerald-600">Favoráveis</dt>
                                                    <dd class="text-sm font-semibold text-emerald-700">{{ $voting->votes_favor }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-red-50 py-2">
                                                    <dt class="text-xs text-red-600">Contrários</dt>
                                                    <dd class="text-sm font-semibold text-red-700">{{ $voting->votes_against }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-amber-50 py-2">
                                                    <dt class="text-xs text-amber-600">Abstenções</dt>
                                                    <dd class="text-sm font-semibold text-amber-700">{{ $voting->votes_abstention }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-orange-50 py-2">
                                                    <dt class="text-xs text-orange-600">Impedidos</dt>
                                                    <dd class="text-sm font-semibold text-orange-700">{{ $voting->votes_impeded }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-slate-50 py-2">
                                                    <dt class="text-xs text-slate-500">Ausentes</dt>
                                                    <dd class="text-sm font-semibold text-slate-600">{{ $voting->votes_absent }}</dd>
                                                </div>
                                            </dl>

                                            @if ($voting->records->isNotEmpty())
                                                <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                                    @foreach ($voting->records as $record)
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2 py-1 text-xs text-slate-600">
                                                            {{ $record->councilor?->name }}
                                                            <span class="font-medium text-slate-800">{{ $record->vote->label() }}</span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs text-slate-400">
                                    A situação da proposição só se altera por resultado formal de votação, validado pelo quórum configurado.
                                </p>
                            @endif
                        </div>

                        {{-- Encaminhamentos pós-votação --}}
                        <div>
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Encaminhamentos pós-votação</h3>

                            @if ($canPostVoting)
                                <form method="POST" action="{{ route('propositions.post_voting.store', $proposition) }}"
                                      class="grid grid-cols-1 gap-3 border-b border-slate-100 pb-4 sm:grid-cols-2">
                                    @csrf
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Situação</label>
                                        <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            @foreach ($postVotingStatuses as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Votação vinculada (opcional)</label>
                                        <select name="voting_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="">Nenhuma</option>
                                            @foreach ($proposition->votings as $voting)
                                                <option value="{{ $voting->id }}">
                                                    {{ $voting->type->label() }} · {{ $voting->turno }}º turno
                                                    @if ($voting->result) — {{ $voting->result->label() }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('voting_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                                        <input type="text" name="notes" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <button type="submit"
                                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                            Registrar encaminhamento
                                        </button>
                                    </div>
                                </form>
                            @endif

                            <ol class="mt-4 space-y-3">
                                @forelse ($proposition->postVotingActions as $action)
                                    <li class="rounded-xl border border-slate-200 p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <x-badge :color="$action->status->badge()">{{ $action->status->label() }}</x-badge>
                                                <p class="mt-1 text-xs text-slate-400">
                                                    {{ $action->performed_at?->format('d/m/Y H:i') }}
                                                    @if ($action->performer) · {{ $action->performer->name }} @endif
                                                    @if ($action->voting) · Sessão nº {{ $action->voting->session?->number }} @endif
                                                </p>
                                                @if ($action->notes)
                                                    <p class="mt-1 whitespace-pre-line text-xs text-slate-600">{{ $action->notes }}</p>
                                                @endif
                                            </div>
                                            @if ($canPostVoting)
                                                <details>
                                                    <summary class="cursor-pointer text-xs font-medium text-red-600">Remover</summary>
                                                    <form method="POST" action="{{ route('propositions.post_voting.destroy', $action) }}"
                                                          class="mt-2 flex items-end gap-2">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="text" name="justification" required placeholder="Justificativa"
                                                               class="w-48 rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                                                        <button type="submit"
                                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                            Confirmar
                                                        </button>
                                                    </form>
                                                </details>
                                            @endif
                                        </div>
                                    </li>
                                @empty
                                    <li class="py-4 text-center text-sm text-slate-400">Nenhum encaminhamento registrado.</li>
                                @endforelse
                            </ol>
                        </div>
                    </div>

                    {{-- Documentos --}}
                    <div data-panel="documentos">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Anexos</h3>
                        <ul class="divide-y divide-slate-100">
                            @forelse ($proposition->attachments as $attachment)
                                <li class="flex items-center justify-between gap-3 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-700">{{ $attachment->original_name }}</p>
                                        <p class="text-xs text-slate-400">
                                            {{ $attachment->humanSize() }}
                                            · {{ $attachment->created_at->format('d/m/Y H:i') }}
                                            · {{ $attachment->uploader?->name ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('propositions.attachments.download', $attachment) }}"
                                           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Baixar
                                        </a>
                                        @if ($canCreate)
                                            <form method="POST" action="{{ route('propositions.attachments.destroy', $attachment) }}"
                                                  onsubmit="return confirm('Remover este anexo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                    Excluir
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="py-4 text-center text-sm text-slate-400">Nenhum anexo.</li>
                            @endforelse
                        </ul>

                        @if ($canCreate)
                            <form method="POST" action="{{ route('propositions.attachments.store', $proposition) }}"
                                  enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                                @csrf
                                <div class="min-w-[240px] flex-1">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Novo anexo</label>
                                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    @error('file') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="submit"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Enviar anexo
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Histórico --}}
                    <div data-panel="historico">
                        @include('propositions.partials.timeline', ['timeline' => $timeline])
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Ações</h3>

                <div class="space-y-4">
                    @if ($canCreate && in_array($proposition->status, [\App\Enums\PropositionStatus::RASCUNHO, \App\Enums\PropositionStatus::DEVOLVIDA], true))
                        <form method="POST" action="{{ route('propositions.submit', $proposition) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Enviar para análise
                            </button>
                        </form>
                    @endif

                    @if ($canRegister && $proposition->status === \App\Enums\PropositionStatus::APROVADA)
                        <form method="POST" action="{{ route('propositions.register', $proposition) }}"
                              onsubmit="return confirm('Confirmar o registro oficial desta proposição?');">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Registrar oficialmente
                            </button>
                        </form>
                    @endif

                    @if ($canReview && in_array($proposition->status, [
                        \App\Enums\PropositionStatus::ENVIADA,
                        \App\Enums\PropositionStatus::EM_ANALISE,
                        \App\Enums\PropositionStatus::REENVIADA,
                    ], true))
                        <form method="POST" action="{{ route('propositions.review', $proposition) }}" class="space-y-3 border-t border-slate-100 pt-4">
                            @csrf
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Decisão da análise</label>
                                <select name="decision" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="em_analise">Em análise</option>
                                    <option value="aprovada">Aprovada</option>
                                    <option value="devolvida">Devolvida</option>
                                    <option value="rejeitada">Rejeitada</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                                <textarea name="notes" rows="3"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                                Registrar análise
                            </button>
                        </form>
                    @endif

                    @unless ($canCreate || $canReview || $canRegister)
                        <p class="text-sm text-slate-400">Nenhuma ação disponível.</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const root = document.getElementById('proposition-tabs');
            if (!root) return;

            const tabs = root.querySelectorAll('[data-tab]');
            const panels = root.querySelectorAll('[data-panel]');

            function activate(name) {
                tabs.forEach(function (tab) {
                    const on = tab.dataset.tab === name;
                    tab.classList.toggle('bg-white', on);
                    tab.classList.toggle('text-indigo-600', on);
                    tab.classList.toggle('border-b-2', on);
                    tab.classList.toggle('border-indigo-600', on);
                    tab.classList.toggle('text-slate-500', !on);
                });
                panels.forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.dataset.panel !== name);
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(tab.dataset.tab);
                });
            });

            activate(@json($activeTab));
        })();
    </script>
    @endpush
@endsection
