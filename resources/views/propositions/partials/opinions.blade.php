@php
    $user = auth()->user();
    $hasGlobalOpinion = $user->hasPermission('propositions.opinion');

    // Comissões em que se pode registrar parecer: todas, se o usuário tiver a
    // permissão global; caso contrário, apenas onde é relator ou presidente.
    $opinionCommissionIds = $hasGlobalOpinion
        ? $commissions->pluck('id')->map(fn ($id) => (int) $id)->all()
        : $user->commissionIdsWithRole(['relator', 'presidente']);

    $opinionCommissions = $commissions->whereIn('id', $opinionCommissionIds);
    $opinionForwardings = $proposition->forwardings->whereIn('commission_id', $opinionCommissionIds);
    $canOpinion = $opinionCommissions->isNotEmpty();

    $canManageOpinion = fn ($opinion) => $hasGlobalOpinion
        || $user->hasCommissionRole((int) $opinion->commission_id, ['relator', 'presidente']);
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Pareceres das comissões</h3>
        <span class="text-xs text-slate-400">{{ $proposition->opinions->count() }} parecer(es)</span>
    </div>

    <p class="mt-1 text-xs text-slate-400">
        O parecer da comissão não equivale à aprovação da matéria pelo Plenário.
    </p>

    @if ($canOpinion)
        <form method="POST" action="{{ route('propositions.opinions.store', $proposition) }}"
              class="mt-4 grid grid-cols-1 gap-3 border-b border-slate-100 pb-4 sm:grid-cols-2">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Comissão</label>
                <select name="commission_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Selecione…</option>
                    @foreach ($opinionCommissions as $commission)
                        <option value="{{ $commission->id }}">
                            {{ $commission->acronym ? $commission->acronym.' — ' : '' }}{{ $commission->name }}
                        </option>
                    @endforeach
                </select>
                @error('commission_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Encaminhamento vinculado</label>
                <select name="forwarding_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Nenhum —</option>
                    @foreach ($opinionForwardings as $forwarding)
                        <option value="{{ $forwarding->id }}">
                            {{ $forwarding->commission?->acronym ?? $forwarding->commission?->name }}
                            · {{ $forwarding->forwarded_at?->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Relator</label>
                <select name="rapporteur_councilor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Não aplicável —</option>
                    @foreach ($councilors as $councilor)
                        <option value="{{ $councilor->id }}">{{ $councilor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Resultado</label>
                <select name="result" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($opinionResults as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('result') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Data do parecer</label>
                <input type="date" name="opinion_date" value="{{ now()->toDateString() }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Documento anexado</label>
                <select name="proposition_attachment_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Nenhum —</option>
                    @foreach ($proposition->attachments as $attachment)
                        <option value="{{ $attachment->id }}">{{ $attachment->original_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Texto do parecer</label>
                <textarea name="text" rows="4" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Registrar parecer
                </button>
            </div>
        </form>
    @endif

    <div class="mt-4 space-y-4">
        @forelse ($proposition->opinions as $opinion)
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $opinion->commission?->acronym ? $opinion->commission->acronym.' — ' : '' }}{{ $opinion->commission?->name }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $opinion->opinion_date?->format('d/m/Y') ?? '—' }}
                            @if ($opinion->rapporteur) · Relator: {{ $opinion->rapporteur->name }} @endif
                            @if ($opinion->registrar) · Registrado por {{ $opinion->registrar->name }} @endif
                        </p>
                    </div>
                    <x-badge :color="$opinion->result->badge()">{{ $opinion->result->label() }}</x-badge>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $opinion->text }}</p>

                @if ($opinion->attachment)
                    <a href="{{ route('propositions.attachments.download', $opinion->attachment) }}"
                       class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">
                        {{ $opinion->attachment->original_name }}
                    </a>
                @endif

                @if ($canManageOpinion($opinion))
                    <details class="mt-3 border-t border-slate-100 pt-3">
                        <summary class="cursor-pointer text-xs font-medium text-red-600">Remover parecer</summary>
                        <form method="POST" action="{{ route('propositions.opinions.destroy', $opinion) }}"
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
                @endif
            </div>
        @empty
            <p class="py-4 text-center text-sm text-slate-400">Nenhum parecer registrado.</p>
        @endforelse
    </div>
</div>
