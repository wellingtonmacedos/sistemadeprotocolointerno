@php
    $user = auth()->user();
    $canForward = $user->hasPermission('propositions.forward');
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Encaminhamentos para comissões</h3>
        <span class="text-xs text-slate-400">{{ $proposition->forwardings->count() }} registro(s)</span>
    </div>

    @if ($canForward)
        <form method="POST" action="{{ route('propositions.forwardings.store', $proposition) }}"
              class="mt-4 grid grid-cols-1 gap-3 border-b border-slate-100 pb-4 sm:grid-cols-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Comissão</label>
                <select name="commission_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Selecione…</option>
                    @foreach ($commissions as $commission)
                        <option value="{{ $commission->id }}">
                            {{ $commission->acronym ? $commission->acronym.' — ' : '' }}{{ $commission->name }}
                        </option>
                    @endforeach
                </select>
                @error('commission_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Prazo</label>
                <input type="date" name="deadline" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('deadline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Encaminhar
                </button>
            </div>
            <div class="sm:col-span-4">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observações do encaminhamento</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
        </form>
    @endif

    <div class="mt-4 space-y-4">
        @forelse ($proposition->forwardings as $forwarding)
            @php
                // Receber/movimentar: quem tem a permissão global ou é membro ativo da comissão.
                $canAct = $canForward || $user->isActiveCommissionMember((int) $forwarding->commission_id);
            @endphp
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $forwarding->commission?->acronym ? $forwarding->commission->acronym.' — ' : '' }}{{ $forwarding->commission?->name }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            Encaminhada em {{ $forwarding->forwarded_at?->format('d/m/Y H:i') ?? '—' }}
                            @if ($forwarding->forwardedBy) por {{ $forwarding->forwardedBy->name }} @endif
                            @if ($forwarding->deadline) · Prazo: {{ $forwarding->deadline->format('d/m/Y') }} @endif
                        </p>
                        @if ($forwarding->received_at)
                            <p class="mt-1 text-xs text-emerald-600">
                                Recebida em {{ $forwarding->received_at->format('d/m/Y H:i') }}
                                @if ($forwarding->receivedBy) por {{ $forwarding->receivedBy->name }} @endif
                            </p>
                        @endif
                        @if ($forwarding->notes)
                            <p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ $forwarding->notes }}</p>
                        @endif
                    </div>
                    <x-badge :color="$forwarding->status->badge()">{{ $forwarding->status->label() }}</x-badge>
                </div>

                @if ($canAct || $canForward)
                    <div class="mt-3 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
                        @if ($canAct)
                            @unless ($forwarding->received_at)
                                <form method="POST" action="{{ route('propositions.forwardings.receive', $forwarding) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                        Confirmar recebimento
                                    </button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('propositions.forwardings.status', $forwarding) }}"
                                  class="flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Situação</label>
                                    <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                        @foreach ($forwardingStatuses as $value => $label)
                                            <option value="{{ $value }}" @selected($forwarding->status->value === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Atualizar
                                </button>
                            </form>
                        @endif

                        @if ($canForward)
                            <details class="w-full">
                                <summary class="cursor-pointer text-xs font-medium text-red-600">Cancelar encaminhamento</summary>
                                <form method="POST" action="{{ route('propositions.forwardings.destroy', $forwarding) }}"
                                      class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('DELETE')
                                    <input type="text" name="justification" required placeholder="Justificativa"
                                           class="min-w-[240px] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                                    <button type="submit"
                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                        Cancelar
                                    </button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="py-4 text-center text-sm text-slate-400">Nenhum encaminhamento registrado.</p>
        @endforelse
    </div>
</div>

@include('propositions.partials.opinions', [
    'proposition' => $proposition,
    'commissions' => $commissions,
    'councilors' => $councilors,
    'opinionResults' => $opinionResults,
])
