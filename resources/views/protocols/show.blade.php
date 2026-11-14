@extends('layouts.app')

@section('title', 'Protocolo '.$protocol->formatted_number)
@section('subtitle', \Illuminate\Support\Str::limit($protocol->subject, 80))

@section('actions')
    <a href="{{ route('protocols.receipt', $protocol) }}" target="_blank"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Comprovante PDF
    </a>
    <a href="{{ route('protocols.mirror', $protocol) }}" target="_blank"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Espelho PDF
    </a>
    @if (auth()->user()->hasPermission('protocols.edit'))
        <a href="{{ route('protocols.edit', $protocol) }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Editar
        </a>
    @endif
@endsection

@section('content')
    @php
        $user = auth()->user();
        $andamentoTypes = [
            \App\Enums\MovementType::DESPACHO,
            \App\Enums\MovementType::PARECER,
            \App\Enums\MovementType::DEVOLUCAO,
            \App\Enums\MovementType::SOLICITACAO_COMPLEMENTACAO,
            \App\Enums\MovementType::CONCLUSAO,
            \App\Enums\MovementType::ARQUIVAMENTO,
        ];
        $pendingForUser = $protocol->movements->filter(function ($movement) use ($user) {
            if (! $movement->isPending()) {
                return false;
            }

            if ($movement->to_commission_id) {
                return $user->hasPermission('protocols.receive_commission');
            }

            return ! $user->sector_id || $movement->to_sector_id === $user->sector_id;
        });
    @endphp

    {{-- Cabeçalho --}}
    @php $deadlineSituation = $protocol->deadlineSituation(); @endphp
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-lg font-semibold text-slate-800">{{ $protocol->formatted_number }}</span>
            <x-badge color="bg-slate-100 text-slate-700">{{ $protocol->document_type->label() }}</x-badge>
            <x-badge :color="$protocol->status->badge()">{{ $protocol->status->label() }}</x-badge>
            <x-badge :color="$protocol->priority->badge()">Prioridade: {{ $protocol->priority->label() }}</x-badge>
            @if ($deadlineSituation !== \App\Enums\DeadlineSituation::SEM_PRAZO)
                <x-badge :color="$deadlineSituation->badge()">Prazo: {{ $deadlineSituation->label() }}</x-badge>
            @endif
        </div>
        <p class="mt-2 text-sm text-slate-600">{{ $protocol->subject }}</p>
        <p class="mt-1 text-xs text-slate-400">
            Registrado em {{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}
            por {{ $protocol->creator?->name ?? '—' }}
            · Responsável atual: {{ $protocol->currentResponsibleLabel() }}
            @if ($protocol->currentPermanenceStartedAt())
                (no destino desde {{ $protocol->currentPermanenceStartedAt()->format('d/m/Y H:i') }}
                · permanência {{ $protocol->currentPermanenceDuration() }})
            @endif
            @if ($protocol->currentDueAt())
                · Data-limite: {{ $protocol->currentDueAt()->format('d/m/Y H:i') }}
            @endif
        </p>
    </div>

    {{-- Abas --}}
    <div class="mt-4 flex gap-1 overflow-x-auto border-b border-slate-200">
        <button type="button" data-tab="dados"
                class="whitespace-nowrap border-b-2 border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600">
            Dados gerais
        </button>
        <button type="button" data-tab="tramitacao"
                class="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
            Tramitação
        </button>
        <button type="button" data-tab="providencias"
                class="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
            Providências
        </button>
        <button type="button" data-tab="ocorrencias"
                class="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
            Ocorrências
        </button>
        <button type="button" data-tab="historico"
                class="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
            Histórico
        </button>
        <button type="button" data-tab="anexos"
                class="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
            Documentos e anexos
        </button>
    </div>

    {{-- Aba: Dados gerais --}}
    <section data-panel="dados" class="mt-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Dados gerais</h2>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tipo de documento</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->document_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Prioridade</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->priority->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Situação</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->status->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Setor de destino</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->destinationSector?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Responsável atual</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->currentResponsibleLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Protocolado em</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Interessado</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->sender_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">CPF/CNPJ</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->sender_document ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->sender_email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Telefone</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $protocol->sender_phone ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $protocol->description ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- Aba: Tramitação --}}
    <section data-panel="tramitacao" class="mt-4 hidden">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @if ($user->hasPermission('protocols.forward') || $user->hasPermission('protocols.dispatch_commission'))
                @php
                    $canForwardSector = $user->hasPermission('protocols.forward');
                    $canForwardCommission = $user->hasPermission('protocols.dispatch_commission');
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Encaminhar protocolo</h2>
                    <form method="POST" action="{{ route('protocols.forward', $protocol) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="forward_destination_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo de destino *</label>
                            <select id="forward_destination_type" name="destination_type" required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @if ($canForwardSector)
                                    <option value="sector">Setor</option>
                                @endif
                                @if ($canForwardCommission)
                                    <option value="commission">Comissão</option>
                                @endif
                            </select>
                        </div>
                        <div id="forward_sector_wrapper" class="{{ $canForwardSector ? '' : 'hidden' }}">
                            <label for="forward_sector" class="mb-1 block text-sm font-medium text-slate-700">Setor de destino *</label>
                            <select id="forward_sector" name="to_sector_id"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Selecione...</option>
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="forward_commission_wrapper" class="{{ $canForwardCommission && ! $canForwardSector ? '' : 'hidden' }}">
                            <label for="forward_commission" class="mb-1 block text-sm font-medium text-slate-700">Comissão de destino *</label>
                            <select id="forward_commission" name="to_commission_id"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Selecione...</option>
                                @foreach ($commissions as $commission)
                                    <option value="{{ $commission->id }}">{{ $commission->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="forward_description" class="mb-1 block text-sm font-medium text-slate-700">Observação</label>
                            <textarea id="forward_description" name="description" rows="2" maxlength="2000"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label for="forward_deadline_days" class="mb-1 block text-sm font-medium text-slate-700">Prazo (dias)</label>
                                <input type="number" id="forward_deadline_days" name="deadline_days" min="1" max="3650"
                                       placeholder="Sem prazo"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="forward_deadline_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo de prazo</label>
                                <select id="forward_deadline_type" name="deadline_type"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">—</option>
                                    @foreach (\App\Enums\DeadlineType::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="forward_deadline_mode" class="mb-1 block text-sm font-medium text-slate-700">Contagem</label>
                                <select id="forward_deadline_mode" name="deadline_mode"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    @foreach (\App\Enums\DeadlineMode::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($value === \App\Enums\DeadlineMode::CORRIDOS->value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="forward_deadline_notes" class="mb-1 block text-sm font-medium text-slate-700">Observação do prazo</label>
                            <input type="text" id="forward_deadline_notes" name="deadline_notes" maxlength="2000"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Encaminhar
                        </button>
                    </form>
                </div>
            @endif

            @if ($user->hasPermission('protocols.receive') || $user->hasPermission('protocols.receive_commission'))
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Recebimentos pendentes</h2>

                    @forelse ($pendingForUser as $movement)
                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 last:mb-0">
                            <p class="text-sm text-amber-800">
                                {{ $movement->type->label() }}
                                <span class="mx-1">→</span>
                                {{ $movement->destinationLabel() }}
                            </p>
                            <p class="mt-0.5 text-xs text-amber-700">
                                De {{ $movement->originLabel() }}
                                · {{ $movement->created_at?->format('d/m/Y H:i') }}
                            </p>
                            @if ($movement->hasDeadline())
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                    <span>{{ $movement->deadlineLabel() }}</span>
                                    <span>· Data-limite {{ $movement->due_at->format('d/m/Y H:i') }}</span>
                                    <x-badge :color="$movement->deadlineSituation()->badge()">{{ $movement->deadlineSituation()->label() }}</x-badge>
                                </p>
                            @endif
                            <form method="POST" action="{{ route('protocols.receive', $movement) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="description" placeholder="Observação (opcional)"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button type="submit"
                                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Confirmar recebimento
                                </button>
                            </form>

                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-medium text-amber-700 hover:underline">
                                    Receber com divergência
                                </summary>
                                <form method="POST" action="{{ route('protocols.receive_divergence', $movement) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <select name="divergence_type" required
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Tipo de divergência *</option>
                                        @foreach (\App\Enums\DivergenceType::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="divergence_description" rows="2" required maxlength="5000"
                                              placeholder="Descrição da divergência *"
                                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                    <input type="text" name="description" maxlength="2000"
                                           placeholder="Observação do recebimento (opcional)"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <button type="submit"
                                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                        Confirmar recebimento com divergência
                                    </button>
                                </form>
                            </details>

                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-medium text-rose-600 hover:underline">
                                    Recusar recebimento
                                </summary>
                                <form method="POST" action="{{ route('protocols.refuse', $movement) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <select name="refusal_reason" required
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Motivo da recusa *</option>
                                        @foreach (\App\Enums\RefusalReason::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="refusal_notes" rows="2" required maxlength="2000"
                                              placeholder="Descrição da ocorrência *"
                                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                    <button type="submit"
                                            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                                        Confirmar recusa
                                    </button>
                                </form>
                            </details>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Nenhum recebimento pendente para você.</p>
                    @endforelse
                </div>
            @endif

            @if ($user->hasPermission('protocols.receive') || $user->hasPermission('protocols.receive_commission'))
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Registrar andamento</h2>
                    <form method="POST" action="{{ route('protocols.movement', $protocol) }}" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @csrf
                        <div>
                            <label for="movement_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo *</label>
                            <select id="movement_type" name="type" required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Selecione...</option>
                                @foreach ($andamentoTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="movement_sector" class="mb-1 block text-sm font-medium text-slate-700">
                                Setor de destino <span class="text-slate-400">(obrigatório na devolução)</span>
                            </label>
                            <select id="movement_sector" name="to_sector_id"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="movement_description" class="mb-1 block text-sm font-medium text-slate-700">Descrição *</label>
                            <textarea id="movement_description" name="description" rows="3" required maxlength="5000"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Registrar andamento
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </section>

    {{-- Aba: Providências --}}
    <section data-panel="providencias" class="mt-4 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Providências registradas</h2>

            @if ($protocol->actions->isEmpty())
                <p class="py-4 text-center text-sm text-slate-400">Nenhuma providência registrada.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data e hora</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsável</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocol->actions as $action)
                                <tr class="border-b border-slate-100 align-top last:border-0">
                                    <td class="px-4 py-3 text-slate-700">{{ $action->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $action->action_type->label() }}
                                        @if ($action->requires_follow_up)
                                            <span class="mt-1 block text-xs font-medium text-amber-600">Acompanhamento necessário</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $action->originLabel() }}</td>
                                    <td class="px-4 py-3 whitespace-pre-line text-slate-700">{{ $action->description }}</td>
                                    <td class="px-4 py-3 whitespace-pre-line text-slate-700">{{ $action->result ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge :color="$action->status->badge()">{{ $action->status->label() }}</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($user->hasPermission('protocols.actions.create'))
                <form method="POST" action="{{ route('protocols.actions.store', $protocol) }}"
                      class="mt-5 grid grid-cols-1 gap-3 border-t border-slate-100 pt-5 md:grid-cols-2">
                    @csrf
                    <div>
                        <label for="action_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo de providência *</label>
                        <select id="action_type" name="action_type" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Selecione...</option>
                            @foreach (\App\Enums\ProtocolActionType::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="requires_follow_up" value="1"
                                   class="rounded border-slate-300 text-indigo-600">
                            Requer acompanhamento
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <label for="action_divergence" class="mb-1 block text-sm font-medium text-slate-700">Divergência relacionada</label>
                        <select id="action_divergence" name="divergence_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            @foreach ($protocol->divergences as $divergence)
                                <option value="{{ $divergence->id }}">
                                    #{{ $divergence->id }} · {{ $divergence->type->label() }} ({{ $divergence->status->label() }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="action_description" class="mb-1 block text-sm font-medium text-slate-700">Descrição *</label>
                        <textarea id="action_description" name="description" rows="3" required maxlength="5000"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label for="action_result" class="mb-1 block text-sm font-medium text-slate-700">Resultado</label>
                        <textarea id="action_result" name="result" rows="2" maxlength="5000"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Registrar providência
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    {{-- Aba: Ocorrências --}}
    <section data-panel="ocorrencias" class="mt-4 hidden">
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Divergências</h2>

                @if ($protocol->divergences->isEmpty())
                    <p class="py-4 text-center text-sm text-slate-400">Nenhuma divergência registrada.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($protocol->divergences as $divergence)
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-800">{{ $divergence->type->label() }}</span>
                                    <x-badge :color="$divergence->status->badge()">{{ $divergence->status->label() }}</x-badge>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $divergence->responsibleLabel() }}
                                    · {{ $divergence->created_at?->format('d/m/Y H:i') }}
                                    @if ($divergence->reporter) · por {{ $divergence->reporter->name }} @endif
                                    @if ($divergence->movement) · movimentação #{{ $divergence->movement->id }} @endif
                                </p>
                                <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $divergence->description }}</p>

                                @if ($divergence->resolution_notes)
                                    <p class="mt-2 whitespace-pre-line rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                                        {{ $divergence->resolution_notes }}
                                        @if ($divergence->resolved_at)
                                            <span class="mt-0.5 block text-xs text-emerald-600">{{ $divergence->resolved_at->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </p>
                                @endif

                                @if ($divergence->actions->isNotEmpty())
                                    <p class="mt-2 text-xs text-slate-500">Providências relacionadas: {{ $divergence->actions->count() }}</p>
                                @endif

                                @if ($user->hasPermission('protocols.actions.manage') && $divergence->isOpen())
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-medium text-indigo-600 hover:underline">Atualizar situação</summary>
                                        <form method="POST" action="{{ route('protocols.divergences.update', $divergence) }}" class="mt-2 space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                @foreach (\App\Enums\DivergenceStatus::options() as $value => $label)
                                                    <option value="{{ $value }}" @selected($value === $divergence->status->value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <textarea name="resolution_notes" rows="2" maxlength="5000"
                                                      placeholder="Observação (obrigatória ao encerrar sem resolução)"
                                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                            <button type="submit"
                                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                                Salvar situação
                                            </button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($user->hasPermission('protocols.actions.create'))
                    <form method="POST" action="{{ route('protocols.divergences.store', $protocol) }}"
                          class="mt-5 grid grid-cols-1 gap-3 border-t border-slate-100 pt-5 md:grid-cols-2">
                        @csrf
                        <div>
                            <label for="divergence_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo de divergência *</label>
                            <select id="divergence_type" name="type" required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Selecione...</option>
                                @foreach (\App\Enums\DivergenceType::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="divergence_movement" class="mb-1 block text-sm font-medium text-slate-700">Movimentação relacionada</label>
                            <select id="divergence_movement" name="protocol_movement_id"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach ($protocol->movements as $movement)
                                    <option value="{{ $movement->id }}">
                                        #{{ $movement->id }} · {{ $movement->type->label() }} → {{ $movement->destinationLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="divergence_description" class="mb-1 block text-sm font-medium text-slate-700">Descrição *</label>
                            <textarea id="divergence_description" name="description" rows="3" required maxlength="5000"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Registrar divergência
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Retificações de movimentações</h2>

                @if ($protocol->movementCorrections->isEmpty())
                    <p class="py-4 text-center text-sm text-slate-400">Nenhuma retificação registrada.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($protocol->movementCorrections as $correction)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-sm font-medium text-amber-800">
                                    Movimentação #{{ $correction->protocol_movement_id }}
                                    @if ($correction->movement) · {{ $correction->movement->type->label() }} @endif
                                </p>
                                <p class="mt-0.5 text-xs text-amber-700">
                                    {{ $correction->created_at?->format('d/m/Y H:i') }}
                                    @if ($correction->user) · por {{ $correction->user->name }} @endif
                                </p>
                                <p class="mt-2 text-sm text-slate-700"><span class="font-medium">Motivo:</span> {{ $correction->reason }}</p>

                                <div class="mt-2 overflow-x-auto rounded-lg border border-amber-200">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-amber-100 text-amber-800">
                                            <tr>
                                                <th class="px-3 py-1.5 font-medium">Campo</th>
                                                <th class="px-3 py-1.5 font-medium">Original</th>
                                                <th class="px-3 py-1.5 font-medium">Corrigido</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-amber-100 bg-white">
                                            @foreach ($correction->comparisonRows() as $row)
                                                <tr>
                                                    <td class="px-3 py-1.5 text-slate-500">{{ $row['label'] }}</td>
                                                    <td class="px-3 py-1.5 text-slate-500 line-through">{{ $row['original'] }}</td>
                                                    <td class="px-3 py-1.5 font-medium text-slate-800">{{ $row['corrected'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($user->hasPermission('protocols.movements.correct'))
                    <details class="mt-5 border-t border-slate-100 pt-5">
                        <summary class="cursor-pointer text-sm font-medium text-indigo-600 hover:underline">Registrar retificação</summary>
                        <form method="POST" id="correction_form" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            @csrf
                            <div class="md:col-span-2">
                                <label for="correction_movement" class="mb-1 block text-sm font-medium text-slate-700">Movimentação *</label>
                                <select id="correction_movement" name="movement_id" required
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Selecione...</option>
                                    @foreach ($protocol->movements as $movement)
                                        <option value="{{ route('protocols.movements.correct', $movement) }}">
                                            #{{ $movement->id }} · {{ $movement->type->label() }} → {{ $movement->destinationLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="correction_reason" class="mb-1 block text-sm font-medium text-slate-700">Motivo da retificação *</label>
                                <textarea id="correction_reason" name="reason" rows="2" required maxlength="5000"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div>
                                <label for="correction_description" class="mb-1 block text-sm font-medium text-slate-700">Nova descrição</label>
                                <textarea id="correction_description" name="description" rows="2" maxlength="5000"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div>
                                <label for="correction_type" class="mb-1 block text-sm font-medium text-slate-700">Novo tipo de encaminhamento</label>
                                <select id="correction_type" name="type"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Manter</option>
                                    @foreach (\App\Enums\MovementType::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="correction_deadline_days" class="mb-1 block text-sm font-medium text-slate-700">Novo prazo (dias)</label>
                                <input type="number" id="correction_deadline_days" name="deadline_days" min="1" max="3650"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="correction_deadline_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo do prazo</label>
                                <select id="correction_deadline_type" name="deadline_type"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Manter</option>
                                    @foreach (\App\Enums\DeadlineType::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="correction_deadline_mode" class="mb-1 block text-sm font-medium text-slate-700">Contagem do prazo</label>
                                <select id="correction_deadline_mode" name="deadline_mode"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    @foreach (\App\Enums\DeadlineMode::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="correction_deadline_notes" class="mb-1 block text-sm font-medium text-slate-700">Observação do prazo</label>
                                <input type="text" id="correction_deadline_notes" name="deadline_notes" maxlength="2000"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            @if ($user->hasPermission('protocols.movements.correct_destination'))
                                <div class="md:col-span-2">
                                    <label for="correction_destination" class="mb-1 block text-sm font-medium text-slate-700">
                                        Novo destino <span class="text-xs font-normal text-amber-600">(exige autorização elevada)</span>
                                    </label>
                                    <select id="correction_destination" name="destination"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Manter destino atual</option>
                                        <optgroup label="Setores">
                                            @foreach ($sectors as $sector)
                                                <option value="sector:{{ $sector->id }}">{{ $sector->name }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Comissões">
                                            @foreach ($commissions as $commission)
                                                <option value="commission:{{ $commission->id }}">{{ $commission->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                            @endif
                            <div class="md:col-span-2">
                                <button type="submit"
                                        class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                                    Registrar retificação
                                </button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Recusas de recebimento</h2>
                @php $refusedMovements = $protocol->movements->filter(fn ($m) => $m->isRefused()); @endphp
                @if ($refusedMovements->isEmpty())
                    <p class="py-4 text-center text-sm text-slate-400">Nenhuma recusa registrada.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($refusedMovements as $movement)
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                                <p class="text-sm font-medium text-rose-700">Recebimento recusado: {{ $movement->refusalLabel() }}</p>
                                <p class="mt-0.5 text-xs text-rose-600">
                                    {{ $movement->destinationLabel() }}
                                    · {{ $movement->refused_at?->format('d/m/Y H:i') }}
                                    @if ($movement->refusedBy) · por {{ $movement->refusedBy->name }} @endif
                                </p>
                                @if ($movement->refusal_notes)
                                    <p class="mt-1 whitespace-pre-line text-sm text-rose-700">{{ $movement->refusal_notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Aba: Histórico --}}
    <section data-panel="historico" class="mt-4 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico de tramitação</h2>
            @include('protocols.partials.timeline', ['movements' => $protocol->movements])
        </div>
    </section>

    {{-- Aba: Documentos e anexos --}}
    <section data-panel="anexos" class="mt-4 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Documentos e anexos</h2>

            @if ($protocol->attachments->isEmpty())
                <p class="py-4 text-center text-sm text-slate-400">Nenhum anexo cadastrado.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivo</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tamanho</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Enviado por</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocol->attachments as $attachment)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="px-4 py-3 text-slate-700">{{ $attachment->original_name }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $attachment->humanSize() }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $attachment->uploader?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $attachment->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('protocols.attachments.download', $attachment) }}"
                                               class="text-xs font-medium text-indigo-600 hover:underline">Download</a>
                                            @if (auth()->user()->hasPermission('protocols.edit'))
                                                <form method="POST" action="{{ route('protocols.attachments.destroy', $attachment) }}"
                                                      onsubmit="return confirm('Remover este anexo?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">
                                                        Excluir
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($user->hasPermission('protocols.edit'))
                <form method="POST" action="{{ route('protocols.attachments.store', $protocol) }}"
                      enctype="multipart/form-data" class="mt-4 border-t border-slate-100 pt-4">
                    @csrf
                    <label for="new_attachment" class="mb-1 block text-sm font-medium text-slate-700">Novo anexo</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="file" id="new_attachment" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Enviar anexo
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Formatos aceitos: PDF, JPG, JPEG e PNG.</p>
                </form>
            @endif
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.dataset.tab;

                document.querySelectorAll('[data-tab]').forEach(function (other) {
                    const active = other === button;
                    other.classList.toggle('border-indigo-600', active);
                    other.classList.toggle('text-indigo-600', active);
                    other.classList.toggle('border-transparent', !active);
                    other.classList.toggle('text-slate-500', !active);
                });

                document.querySelectorAll('[data-panel]').forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.dataset.panel !== target);
                });
            });
        });

        (function () {
            const typeSelect = document.getElementById('forward_destination_type');
            const sectorWrapper = document.getElementById('forward_sector_wrapper');
            const commissionWrapper = document.getElementById('forward_commission_wrapper');

            if (! typeSelect || ! sectorWrapper || ! commissionWrapper) {
                return;
            }

            function sync() {
                const isCommission = typeSelect.value === 'commission';
                sectorWrapper.classList.toggle('hidden', isCommission);
                commissionWrapper.classList.toggle('hidden', ! isCommission);
            }

            typeSelect.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
