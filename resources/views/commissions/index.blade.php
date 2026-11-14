@extends('layouts.app')

@section('title', 'Comissões')
@section('subtitle', 'Comissões legislativas permanentes e temporárias')

@section('actions')
    @if (auth()->user()->hasPermission('commissions.manage'))
        <a href="{{ route('commissions.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Nova comissão
        </a>
    @endif
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sigla</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Composição vigente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($commissions as $commission)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $commission->acronym }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $commission->name }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ \App\Enums\CommissionType::tryFrom($commission->type)?->label() ?? $commission->type }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $commission->current_members_count }} membro(s)</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$commission->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                            {{ $commission->is_active ? 'Ativa' : 'Inativa' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('commissions.show', $commission) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Ver
                            </a>
                            @if (auth()->user()->hasPermission('commissions.manage'))
                                <a href="{{ route('commissions.edit', $commission) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma comissão cadastrada.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $commissions->links() }}</div>
@endsection
