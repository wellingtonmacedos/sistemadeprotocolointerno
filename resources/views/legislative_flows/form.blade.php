@extends('layouts.app')

@section('title', 'Novo fluxo legislativo')
@section('subtitle', 'A configuração inicial sugerida poderá ser revisada antes da publicação')

@section('content')
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('legislative-flows.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Modalidade</label>
                <select name="modality" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Selecione —</option>
                    @foreach (\App\Enums\PropositionModality::options() as $value => $label)
                        @continue(in_array($value, $usedModalities, true))
                        <option value="{{ $value }}" @selected(old('modality') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('modality') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if (count($usedModalities) > 0)
                    <p class="mt-1 text-xs text-slate-500">
                        Modalidades já configuradas não aparecem nesta lista (um fluxo por modalidade).
                    </p>
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nome do fluxo</label>
                <input name="name" value="{{ old('name', 'Fluxo padrão') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                <textarea name="description" rows="2"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Câmara</label>
                    <select name="institution_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected((int) old('institution_id') === $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Criar fluxo
                </button>
                <a href="{{ route('legislative-flows.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>
    </div>
@endsection
