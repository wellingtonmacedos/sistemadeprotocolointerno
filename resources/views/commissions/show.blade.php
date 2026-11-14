@extends('layouts.app')

@section('title', 'Comissão '.$commission->acronym)
@section('subtitle', $commission->name)

@section('actions')
    @if (auth()->user()->hasPermission('commissions.manage'))
        <a href="{{ route('commissions.edit', $commission) }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Editar comissão
        </a>
    @endif
    <a href="{{ route('commissions.index') }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Voltar
    </a>
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-lg bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
                        {{ $commission->acronym }}
                    </span>
                    <x-badge :color="$commission->type === 'permanente' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">
                        {{ \App\Enums\CommissionType::tryFrom($commission->type)?->label() ?? $commission->type }}
                    </x-badge>
                    <x-badge :color="$commission->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                        {{ $commission->is_active ? 'Ativa' : 'Inativa' }}
                    </x-badge>
                </div>

                @if ($commission->description)
                    <p class="mt-4 text-sm text-slate-600">{{ $commission->description }}</p>
                @endif

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Presidente</dt>
                        <dd class="text-slate-800">{{ $commission->president()?->councilor?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vice-presidente</dt>
                        <dd class="text-slate-800">{{ $commission->vicePresident()?->councilor?->name ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($commission->competences)
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Competências</p>
                        <p class="whitespace-pre-line text-sm text-slate-700">{{ $commission->competences }}</p>
                    </div>
                @endif

                @if (! empty($commission->allowed_modalities))
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modalidades que analisa</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach ($commission->allowed_modalities as $modality)
                                <x-badge color="bg-slate-100 text-slate-700">
                                    {{ \App\Enums\PropositionModality::tryFrom($modality)?->label() ?? $modality }}
                                </x-badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @php
                $canManageComposition = auth()->user()->hasPermission('commissions.composition')
                    || auth()->user()->hasPermission('commissions.manage');
            @endphp

            @if ($canManageComposition)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-slate-800">Adicionar integrante à composição</h2>
                    <form method="POST" action="{{ route('commissions.members.store', $commission) }}"
                          class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Vereador</label>
                            <select name="councilor_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">— Selecione —</option>
                                @foreach ($councilors as $councilor)
                                    <option value="{{ $councilor->id }}" @selected((int) old('councilor_id') === $councilor->id)>
                                        {{ $councilor->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('councilor_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Cargo</label>
                            <select name="role" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', 'membro') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Início do mandato</label>
                            <input type="date" name="start_date" required value="{{ old('start_date', now()->toDateString()) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @error('start_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Término (opcional)</label>
                            <input type="date" name="end_date" value="{{ old('end_date') }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @error('end_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Observações</label>
                            <input name="notes" value="{{ old('notes') }}" maxlength="255"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Adicionar
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-800">
                    Composição vigente
                </h2>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Vereador</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Início</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($commission->currentMembers as $member)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $member->councilor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ \App\Enums\CommissionMemberRole::tryFrom($member->role)?->label() ?? $member->role }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $member->start_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canManageComposition)
                                    <form method="POST" action="{{ route('commissions.members.end', $member) }}"
                                          class="flex items-center justify-end gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="date" name="end_date" required value="{{ now()->toDateString() }}"
                                               class="rounded-lg border border-slate-300 px-2 py-1 text-xs">
                                        <button type="submit"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Encerrar mandato
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                Nenhum integrante na composição vigente.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-800">
                Histórico de composição
            </h2>
            <ul class="divide-y divide-slate-50">
                @forelse ($commission->members->whereNotNull('end_date') as $member)
                    <li class="px-6 py-3 text-sm">
                        <p class="font-medium text-slate-800">{{ $member->councilor?->name ?? '—' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ \App\Enums\CommissionMemberRole::tryFrom($member->role)?->label() ?? $member->role }}
                            · {{ $member->start_date?->format('d/m/Y') }} — {{ $member->end_date?->format('d/m/Y') }}
                        </p>
                    </li>
                @empty
                    <li class="px-6 py-6 text-center text-sm text-slate-500">Sem histórico anterior.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
