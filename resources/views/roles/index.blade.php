@extends('layouts.app')

@section('title', 'Perfis e Permissões')
@section('subtitle', 'Configure o que cada perfil pode acessar')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Permissões</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Usuários</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($roles as $role)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3">
                        <span class="font-medium text-slate-800">{{ $role->name }}</span>
                        <p class="text-xs text-slate-400">{{ $role->slug }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $role->permissions_count }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $role->users_count }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            @if (auth()->user()->hasPermission('roles.manage'))
                                <a href="{{ route('roles.edit', $role) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Editar permissões
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum perfil cadastrado.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
