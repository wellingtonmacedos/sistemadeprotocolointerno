@extends('layouts.app')

@section('title', 'Editar proposição')
@section('subtitle', 'Atualize os dados da proposição legislativa')

@section('content')
    @php
        $selectedCoauthors = collect(old('coauthor_ids', $proposition->authors->pluck('id')->all()));
    @endphp

    <form method="POST" action="{{ route('propositions.update', $proposition) }}" enctype="multipart/form-data"
          class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Dados da proposição</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidade *</label>
                    <select name="modality" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        @foreach ($modalities as $value => $label)
                            <option value="{{ $value }}" @selected(old('modality', $proposition->modality->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('modality') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Origem *</label>
                    <select name="origin" id="proposition-origin" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($origins as $value => $label)
                            <option value="{{ $value }}" @selected(old('origin', $proposition->origin->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Executivo: projetos de lei, vetos e mensagens enviados pelo Prefeito.</p>
                    @error('origin') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Autor (vereador) *</label>
                    <select name="author_councilor_id" id="author-councilor"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        @foreach ($councilors as $councilor)
                            <option value="{{ $councilor->id }}"
                                    @selected((string) old('author_councilor_id', $proposition->author_councilor_id) === (string) $councilor->id)>
                                {{ $councilor->name }}{{ $councilor->party ? ' · '.$councilor->party : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('author_councilor_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 hidden">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Autor externo (nome ou cargo) *</label>
                    <input name="author_external_name" id="author-external"
                           value="{{ old('author_external_name', $proposition->author_external_name) }}"
                           placeholder="Ex.: Prefeito Municipal, Secretaria de Saúde"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('author_external_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Coautores</label>
                    <select name="coauthor_ids[]" multiple size="5"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($councilors as $councilor)
                            <option value="{{ $councilor->id }}" @selected($selectedCoauthors->contains($councilor->id))>
                                {{ $councilor->name }}{{ $councilor->party ? ' · '.$councilor->party : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Use Ctrl (ou Cmd) para selecionar mais de um vereador.</p>
                    @error('coauthor_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('coauthor_ids.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Título *</label>
                    <input name="title" value="{{ old('title', $proposition->title) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ementa *</label>
                    <textarea name="ementa" rows="3" required
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('ementa', $proposition->ementa) }}</textarea>
                    @error('ementa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Texto</label>
                    <textarea name="text" rows="8"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('text', $proposition->text) }}</textarea>
                    @error('text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Justificativa</label>
                    <textarea name="justification" rows="5"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('justification', $proposition->justification) }}</textarea>
                    @error('justification') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                    <textarea name="observations" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('observations', $proposition->observations) }}</textarea>
                    @error('observations') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Adicionar anexos</label>
                    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">Formatos aceitos: PDF, JPG, JPEG e PNG.</p>
                    @error('attachments') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('attachments.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('propositions.show', $proposition) }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Salvar alterações
            </button>
        </div>
    </form>

    @push('scripts')
        @include('propositions.partials.author-toggle')
    @endpush
@endsection
