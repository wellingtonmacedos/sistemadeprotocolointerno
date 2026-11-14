@php
    $step = $step ?? null;
    $boolFields = [
        'is_required' => 'Obrigatória',
        'can_skip' => 'Pode ser pulada (com justificativa)',
        'requires_document' => 'Exige documento',
        'allows_return' => 'Permite devolução',
        'is_conclusive' => 'Conclui o fluxo',
    ];
@endphp

<div class="flow-step-row rounded-xl border border-slate-200 bg-white p-4" data-step-row>
    <div class="flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
            Etapa <span data-step-number></span>
        </p>
        <button type="button" data-remove-step
                class="text-xs font-medium text-red-600 hover:text-red-700">Remover</button>
    </div>

    <input type="hidden" name="steps[{{ $index }}][id]" value="{{ $step->id ?? '' }}">

    <div class="mt-3 grid gap-3 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">Nome</label>
            <input name="steps[{{ $index }}][name]" value="{{ $step->name ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Tipo de etapa</label>
            <select name="steps[{{ $index }}][step_type]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach ($stepTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($step->step_type ?? 'analise') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Prazo (dias)</label>
            <input type="number" min="0" name="steps[{{ $index }}][deadline_days]"
                   value="{{ $step->deadline_days ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Setor responsável</label>
            <select name="steps[{{ $index }}][sector_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Nenhum —</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected((int) ($step->sector_id ?? 0) === $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Comissão responsável</label>
            <select name="steps[{{ $index }}][commission_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Nenhuma —</option>
                @foreach ($commissions as $commission)
                    <option value="{{ $commission->id }}" @selected((int) ($step->commission_id ?? 0) === $commission->id)>
                        {{ $commission->acronym }} — {{ $commission->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Responsável específico</label>
            <select name="steps[{{ $index }}][responsible_user_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Nenhum —</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($step->responsible_user_id ?? 0) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Situação de conclusão</label>
            <input name="steps[{{ $index }}][conclusion_status]" value="{{ $step->conclusion_status ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">Descrição</label>
            <input name="steps[{{ $index }}][description]" value="{{ $step->description ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">Condição para avançar</label>
            <input name="steps[{{ $index }}][advance_condition]" value="{{ $step->advance_condition ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-4">
        @foreach ($boolFields as $field => $label)
            <label class="flex items-center gap-2 text-xs text-slate-700">
                <input type="hidden" name="steps[{{ $index }}][{{ $field }}]" value="0">
                <input type="checkbox" name="steps[{{ $index }}][{{ $field }}]" value="1"
                       @checked($step ? (bool) $step->{$field} : false)
                       class="h-4 w-4 rounded border-slate-300">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>
