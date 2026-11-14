@extends('layouts.app')

@section('title', 'Câmaras')
@section('subtitle', 'Gerencie as câmaras da plataforma')

@section('actions')
    @if (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('institutions.manage'))
        <a href="{{ route('institutions.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Nova câmara
        </a>
    @endif
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">CNPJ</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade/UF</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($institutions as $institution)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $institution->name }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $institution->cnpj ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $institution->city ?? '—' }}@if ($institution->state)/{{ $institution->state }}@endif
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :color="$institution->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                            {{ $institution->is_active ? 'Ativa' : 'Inativa' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('institutions.edit', $institution) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('institutions.destroy', $institution) }}"
                                  onsubmit="return confirm('Deseja realmente excluir esta câmara?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Nenhuma câmara cadastrada.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $institutions->links() }}</div>
@endsection
