@extends('layouts.app')
@php $institution = $institution ?? null; @endphp

@section('title', $institution ? 'Editar câmara' : 'Nova câmara')
@section('subtitle', $institution ? 'Atualize os dados da câmara' : 'Cadastre uma nova câmara')

@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST"
              action="{{ $institution ? route('institutions.update', $institution) : route('institutions.store') }}"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf
            @if ($institution)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $institution->name ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">CNPJ</label>
                    <input name="cnpj" value="{{ old('cnpj', $institution->cnpj ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('cnpj') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Telefone</label>
                    <input name="phone" value="{{ old('phone', $institution->phone ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Endereço</label>
                    <input name="address" value="{{ old('address', $institution->address ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Cidade</label>
                    <input name="city" value="{{ old('city', $institution->city ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">UF</label>
                        <input name="state" maxlength="2" value="{{ old('state', $institution->state ?? '') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('state') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">CEP</label>
                        <input name="zip" value="{{ old('zip', $institution->zip ?? '') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('zip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $institution->email ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Logotipo</label>
                    @if ($institution?->logo_path)
                        <p class="mb-2 text-xs text-slate-500">Arquivo atual: {{ $institution->logo_path }}</p>
                    @endif
                    <input type="file" name="logo" accept="image/*"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       @checked((bool) old('is_active', $institution->is_active ?? true))
                       class="h-4 w-4 rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Câmara ativa</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar
                </button>
                <a href="{{ route('institutions.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
