@extends('layouts.app')

@section('title', 'Configuração de quórum')
@section('subtitle', 'Regras de quórum por instituição e modalidade de proposição')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Modalidade</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Regra</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Presença mínima</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($settings as $setting)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-4 py-3 text-slate-700">
                                {{ $setting->modality ? ($modalities[$setting->modality] ?? $setting->modality) : 'Padrão (todas as modalidades)' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-800">{{ $setting->rule->label() }}</span>
                                <p class="text-xs text-slate-400">{{ $setting->rule->description() }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $setting->min_presence ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <details class="flex justify-end">
                                    <summary class="cursor-pointer rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                        Remover
                                    </summary>
                                    <form method="POST" action="{{ route('quorum.destroy', $setting) }}"
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                Nenhuma regra de quórum configurada. Sem configuração, aplica-se maioria simples.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Definir regra</h3>
                <form method="POST" action="{{ route('quorum.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Modalidade</label>
                        <select name="modality" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Padrão (todas as modalidades)</option>
                            @foreach ($modalities as $value => $label)
                                <option value="{{ $value }}" @selected(old('modality') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('modality') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Regra de quórum</label>
                        <select name="rule" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($rules as $value => $label)
                                <option value="{{ $value }}" @selected(old('rule') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('rule') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Presença mínima (opcional)</label>
                        <input type="number" name="min_presence" min="1" max="999" value="{{ old('min_presence') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('min_presence') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                        <textarea name="notes" rows="3"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Salvar regra
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
