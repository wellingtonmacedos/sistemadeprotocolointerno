@extends('layouts.app')

@section('title', 'Sessão nº '.$session->number)
@section('subtitle', 'Sessão '.$session->type->label().' · '.$session->date->format('d/m/Y'))

@php
    $canManage = auth()->user()->hasPermission('sessions.manage');
    $canAgenda = auth()->user()->hasPermission('sessions.agenda');
    $canVote = auth()->user()->hasPermission('votings.register');
@endphp

@section('actions')
    <a href="{{ route('sessions.index') }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Voltar
    </a>
    @if ($canManage)
        <a href="{{ route('sessions.edit', $session) }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Editar
        </a>
    @endif
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Dados da sessão --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Sessão {{ $session->type->label() }} nº {{ $session->number }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-800">{{ $session->date->format('d/m/Y') }}</h2>
                    </div>
                    <x-badge :color="$session->status->badge()">{{ $session->status->label() }}</x-badge>
                </div>
                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Horário</dt>
                        <dd class="text-sm text-slate-700">{{ $session->time ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Local</dt>
                        <dd class="text-sm text-slate-700">{{ $session->location ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Presidente</dt>
                        <dd class="text-sm text-slate-700">{{ $session->president?->name ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($session->notes)
                    <p class="mt-4 whitespace-pre-line text-sm text-slate-600">{{ $session->notes }}</p>
                @endif
            </div>

            {{-- Pauta / Ordem do dia --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ordem do dia</h3>
                    <span class="text-xs text-slate-400">{{ $session->agendaItems->count() }} item(ns)</span>
                </div>

                @if ($canAgenda)
                    <form method="POST" action="{{ route('sessions.agenda.store', $session) }}"
                          class="mt-4 grid grid-cols-1 gap-3 border-b border-slate-100 pb-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Proposição</label>
                            <select name="proposition_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Selecione…</option>
                                @foreach ($propositions as $proposition)
                                    <option value="{{ $proposition->id }}">
                                        {{ $proposition->official_number ?? $proposition->modality->abbreviation().'-'.$proposition->id }}
                                        — {{ \Illuminate\Support\Str::limit($proposition->title, 70) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('proposition_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de apreciação</label>
                            <select name="appreciation_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($appreciationTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-4">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="will_discuss" value="1" checked class="rounded border-slate-300">
                                Haverá discussão
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="will_vote" value="1" checked class="rounded border-slate-300">
                                Haverá votação
                            </label>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                            <input type="text" name="notes" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Incluir na pauta
                            </button>
                        </div>
                    </form>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse ($session->agendaItems as $item)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                        {{ $item->position }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $item->proposition?->official_number ?? $item->proposition?->modality->label() }}
                                        </p>
                                        <p class="truncate text-xs text-slate-500">{{ $item->proposition?->title }}</p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ $item->appreciation_type->label() }}
                                            · {{ $item->will_discuss ? 'com discussão' : 'sem discussão' }}
                                            · {{ $item->will_vote ? 'com votação' : 'sem votação' }}
                                        </p>
                                        @if ($item->notes)
                                            <p class="mt-1 whitespace-pre-line text-xs text-slate-600">{{ $item->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                                <x-badge :color="$item->status->badge()">{{ $item->status->label() }}</x-badge>
                            </div>

                            @if ($canAgenda)
                                <div class="mt-3 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('sessions.agenda.move', $item) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" title="Subir"
                                                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">↑</button>
                                        </form>
                                        <form method="POST" action="{{ route('sessions.agenda.move', $item) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" title="Descer"
                                                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">↓</button>
                                        </form>
                                    </div>

                                    <form method="POST" action="{{ route('sessions.agenda.status', $item) }}" class="flex items-end gap-2">
                                        @csrf
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Situação</label>
                                            <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                @foreach ($agendaStatuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($item->status->value === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Atualizar
                                        </button>
                                    </form>

                                    <details class="w-full">
                                        <summary class="cursor-pointer text-xs font-medium text-red-600">Retirar da pauta</summary>
                                        <form method="POST" action="{{ route('sessions.agenda.destroy', $item) }}"
                                              class="mt-2 flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('DELETE')
                                            <input type="text" name="justification" required placeholder="Justificativa"
                                                   class="min-w-[240px] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                                            <button type="submit"
                                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                Retirar
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Nenhuma matéria na pauta.</p>
                    @endforelse
                </div>
            </div>

            {{-- Votações --}}
            @if ($canVote)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Votações</h3>
                        <span class="text-xs text-slate-400">{{ $session->votings->count() }} registro(s)</span>
                    </div>

                    @if ($session->agendaItems->isNotEmpty())
                        <form method="POST" action="{{ route('sessions.votings.store', $session) }}"
                              class="mt-4 grid grid-cols-1 gap-3 border-b border-slate-100 pb-4 sm:grid-cols-2">
                            @csrf
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Item da pauta</label>
                                <select name="session_agenda_item_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Selecione…</option>
                                    @foreach ($session->agendaItems as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->position }}º —
                                            {{ $item->proposition?->official_number ?? $item->proposition?->modality->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('session_agenda_item_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Modalidade</label>
                                <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    @foreach ($votingTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Turno</label>
                                <input type="number" name="turno" min="1" max="9" value="1"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Registrar votação
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="mt-4 border-b border-slate-100 pb-4 text-sm text-slate-400">
                            Inclua matérias na ordem do dia para registrar votações.
                        </p>
                    @endif

                    <div class="mt-4 space-y-4">
                        @forelse ($session->votings as $voting)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $voting->proposition?->official_number ?? $voting->proposition?->title }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ $voting->type->label() }} · {{ $voting->turno }}º turno
                                            @if ($voting->quorum_rule) · Quórum: {{ $voting->quorum_rule->label() }} @endif
                                            @if ($voting->quorum_min_presence) · Presença mínima: {{ $voting->quorum_min_presence }} @endif
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

                                @if (! $voting->result)
                                    @if ($voting->type->requiresIndividualVotes())
                                        <div class="mt-3 border-t border-slate-100 pt-3">
                                            <form method="POST" action="{{ route('sessions.votings.votes.store', $voting) }}"
                                                  class="grid grid-cols-1 items-end gap-2 sm:grid-cols-4">
                                                @csrf
                                                <select name="councilor_id" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                    <option value="">Vereador…</option>
                                                    @foreach ($councilors as $councilor)
                                                        <option value="{{ $councilor->id }}">{{ $councilor->name }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="vote" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                    @foreach ($voteTypes as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="notes" placeholder="Observação"
                                                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                <button type="submit"
                                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                    Registrar voto
                                                </button>
                                            </form>

                                            @if ($voting->records->isNotEmpty())
                                                <ul class="mt-3 divide-y divide-slate-100">
                                                    @foreach ($voting->records as $record)
                                                        <li class="flex items-center justify-between gap-2 py-2">
                                                            <span class="text-xs text-slate-700">{{ $record->councilor?->name }}</span>
                                                            <div class="flex items-center gap-2">
                                                                <x-badge :color="$record->vote->badge()">{{ $record->vote->label() }}</x-badge>
                                                                <form method="POST" action="{{ route('sessions.votings.votes.destroy', $record) }}" class="flex items-center gap-1">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <input type="text" name="justification" required placeholder="Justificativa"
                                                                           class="w-40 rounded-lg border border-slate-300 px-2 py-1 text-xs">
                                                                    <button type="submit" class="text-xs text-red-600 hover:underline">remover</button>
                                                                </form>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('sessions.votings.finalize', $voting) }}"
                                          class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                                        @csrf
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Resultado</label>
                                            <select name="result" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                @foreach ($votingResults as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="min-w-[200px] flex-1">
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Observações</label>
                                            <input type="text" name="notes"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                        </div>
                                        <button type="submit"
                                                class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                                            Registrar resultado
                                        </button>
                                    </form>
                                @else
                                    <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                                        Resultado registrado em {{ $voting->result_registered_at?->format('d/m/Y H:i') }}
                                        @if ($voting->registrar) por {{ $voting->registrar->name }} @endif
                                    </p>
                                @endif

                                @if ($voting->notes)
                                    <p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ $voting->notes }}</p>
                                @endif

                                <details class="mt-3 border-t border-slate-100 pt-3">
                                    <summary class="cursor-pointer text-xs font-medium text-red-600">Remover votação</summary>
                                    <form method="POST" action="{{ route('sessions.votings.destroy', $voting) }}"
                                          class="mt-2 flex flex-wrap items-end gap-2">
                                        @csrf
                                        @method('DELETE')
                                        <input type="text" name="justification" required placeholder="Justificativa"
                                               class="min-w-[240px] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                                        <button type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                            Remover
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-400">Nenhuma votação registrada.</p>
                        @endforelse
                    </div>

                    <p class="mt-3 text-xs text-slate-400">
                        O resultado da votação é ato do Plenário e altera a situação da proposição conforme o quórum configurado.
                    </p>
                </div>
            @endif

            {{-- Documentos da sessão --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Documentos da sessão</h3>

                <ul class="divide-y divide-slate-100">
                    @forelse ($session->documents as $document)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-700">{{ $document->original_name }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $document->humanSize() }} · {{ $document->created_at->format('d/m/Y H:i') }}
                                    · {{ $document->uploader?->name ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('sessions.documents.download', $document) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Baixar
                                </a>
                                @if ($canManage)
                                    <details>
                                        <summary class="cursor-pointer rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                            Excluir
                                        </summary>
                                        <form method="POST" action="{{ route('sessions.documents.destroy', $document) }}"
                                              class="mt-2 flex items-end gap-2">
                                            @csrf
                                            @method('DELETE')
                                            <input type="text" name="justification" required placeholder="Justificativa"
                                                   class="w-56 rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
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
                        <li class="py-4 text-center text-sm text-slate-400">Nenhum documento anexado.</li>
                    @endforelse
                </ul>

                @if ($canManage)
                    <form method="POST" action="{{ route('sessions.documents.store', $session) }}"
                          enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                        @csrf
                        <div class="min-w-[240px] flex-1">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Novo documento</label>
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @error('file') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Anexar
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Ações</h3>
                @if ($canManage)
                    <details>
                        <summary class="cursor-pointer text-sm font-medium text-red-600">Excluir sessão</summary>
                        <form method="POST" action="{{ route('sessions.destroy', $session) }}" class="mt-3 space-y-2">
                            @csrf
                            @method('DELETE')
                            <input type="text" name="justification" required placeholder="Justificativa"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button type="submit"
                                    class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                Confirmar exclusão
                            </button>
                        </form>
                    </details>
                @else
                    <p class="text-sm text-slate-400">Nenhuma ação disponível.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
