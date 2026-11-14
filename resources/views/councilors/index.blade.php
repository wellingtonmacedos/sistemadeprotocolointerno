@extends('layouts.app')

@section('title', 'Vereadores')
@section('subtitle', 'Gerencie os vereadores da câmara')

@section('actions')
    @if (auth()->user()->hasPermission('councilors.manage'))
        <a href="{{ route('councilors.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo vereador
        </a>
    @endif
@endsection

@section('content')
    <form method="GET" action="{{ route('councilors.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[240px] flex-1">
            <label class="mb-1 block text-sm font-medium text-slate-700">Buscar</label>
            <input name="q" value="{{ $q }}" placeholder="Nome ou partido"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
            Buscar
        </button>
        @if ($q !== '')
            <a href="{{ route('councilors.index') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Limpar
            </a>
        @endif
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Partido</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($councilors as $councilor)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $councilor->name }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $councilor->party ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $councilor->email ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $councilor->phone ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$councilor->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                            {{ $councilor->is_active ? 'Ativo' : 'Inativo' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (auth()->user()->hasPermission('councilors.manage'))
                                <a href="{{ route('councilors.edit', $councilor) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('councilors.destroy', $councilor) }}"
                                      onsubmit="return confirm('Deseja realmente excluir este vereador?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                        Excluir
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum vereador encontrado.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $councilors->links() }}</div>
@endsection
