@extends('layouts.app')

@section('title', 'Setores')
@section('subtitle', 'Gerencie os setores administrativos')

@section('actions')
    @if (auth()->user()->hasPermission('sectors.manage'))
        <a href="{{ route('sectors.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo setor
        </a>
    @endif
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsável</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($sectors as $sector)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 text-slate-700">
                        <span class="font-medium text-slate-800">{{ $sector->name }}</span>
                        @if ($sector->description)
                            <p class="text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($sector->description, 60) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $sector->responsible?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$sector->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                            {{ $sector->is_active ? 'Ativo' : 'Inativo' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (auth()->user()->hasPermission('sectors.manage'))
                                <a href="{{ route('sectors.edit', $sector) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('sectors.destroy', $sector) }}"
                                      onsubmit="return confirm('Deseja realmente excluir este setor?')">
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
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum setor cadastrado.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sectors->links() }}</div>
@endsection
