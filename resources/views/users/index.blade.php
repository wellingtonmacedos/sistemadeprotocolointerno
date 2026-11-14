@extends('layouts.app')

@section('title', 'Usuários')
@section('subtitle', 'Gerencie os usuários do sistema')

@section('actions')
    @if (auth()->user()->hasPermission('users.manage'))
        <a href="{{ route('users.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo usuário
        </a>
    @endif
@endsection

@section('content')
    <form method="GET" action="{{ route('users.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[240px] flex-1">
            <label class="mb-1 block text-sm font-medium text-slate-700">Buscar</label>
            <input name="q" value="{{ $q }}" placeholder="Nome ou e-mail"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="min-w-[200px]">
            <label class="mb-1 block text-sm font-medium text-slate-700">Perfil</label>
            <select name="role_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos os perfis</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) $roleId === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
            Filtrar
        </button>
        @if ($q !== '' || $roleId)
            <a href="{{ route('users.index') }}"
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
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Setor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $user->role?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $user->sector?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                            {{ $user->is_active ? 'Ativo' : 'Bloqueado' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (auth()->user()->hasPermission('users.manage'))
                                <a href="{{ route('users.edit', $user) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar
                                </a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.toggle-block', $user) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            {{ $user->is_active ? 'Bloquear' : 'Desbloquear' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                          onsubmit="return confirm('Deseja realmente excluir este usuário?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                            Excluir
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum usuário encontrado.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
