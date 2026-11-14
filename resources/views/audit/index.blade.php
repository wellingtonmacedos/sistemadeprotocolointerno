@extends('layouts.app')

@section('title', 'Auditoria')
@section('subtitle', 'Trilha de operações sensíveis registradas no sistema')

@section('content')
    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Ação</label>
            <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="ex: protocol"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Usuário</label>
            <select name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todos</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">De</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Até</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('audit.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ação</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Usuário</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">IP</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="px-4 py-3">
                        <x-badge color="bg-slate-100 text-slate-700">{{ $log->action }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $log->user?->name ?? 'Sistema' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $log->description ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $log->ip ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum registro de auditoria encontrado.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
