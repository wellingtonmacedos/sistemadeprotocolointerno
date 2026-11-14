@extends('layouts.app')

@section('title', 'Editar permissões')
@section('subtitle', 'Perfil: '.$role->name)

@section('content')
    @php
        $selecionadas = old('permissions', $role->permissions->pluck('slug')->all());
    @endphp

    <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @error('permissions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

        @forelse ($permissions as $group => $items)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {{ $group ?: 'Geral' }}
                </h2>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $permission)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->slug }}"
                                   @checked(in_array($permission->slug, $selecionadas, true))
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300">
                            <span>
                                <span class="block text-sm font-medium text-slate-700">{{ $permission->name }}</span>
                                @if ($permission->description)
                                    <span class="block text-xs text-slate-400">{{ $permission->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400 shadow-sm">
                Nenhuma permissão cadastrada.
            </div>
        @endforelse

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Salvar permissões
            </button>
            <a href="{{ route('roles.index') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Voltar
            </a>
        </div>
    </form>
@endsection
