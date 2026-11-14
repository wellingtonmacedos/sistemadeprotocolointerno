@extends('layouts.app')

@section('title', 'Editar protocolo')
@section('subtitle', $protocol->formatted_number.' · '.\Illuminate\Support\Str::limit($protocol->subject, 60))

@section('actions')
    <a href="{{ route('protocols.show', $protocol) }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('protocols.update', $protocol) }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <span class="text-sm text-slate-500">Protocolo</span>
                <span class="font-semibold text-slate-800">{{ $protocol->formatted_number }}</span>
                <x-badge color="bg-slate-100 text-slate-700">
                    {{ $protocol->document_type->label() }}
                </x-badge>
            </div>
            <p class="text-xs text-slate-400">Número, ano e tipo de documento não podem ser alterados.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Dados do documento</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Assunto *</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject', $protocol->subject) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $protocol->description) }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="destination_sector_id" class="mb-1 block text-sm font-medium text-slate-700">Setor de destino *</label>
                    <select id="destination_sector_id" name="destination_sector_id" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->id }}"
                                @selected((string) old('destination_sector_id', $protocol->destination_sector_id) === (string) $sector->id)>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('destination_sector_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="priority" class="mb-1 block text-sm font-medium text-slate-700">Prioridade *</label>
                    <select id="priority" name="priority" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $protocol->priority->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Situação *</label>
                    <select id="status" name="status" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $protocol->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Interessado</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="sender_name" class="mb-1 block text-sm font-medium text-slate-700">Nome *</label>
                    <input type="text" id="sender_name" name="sender_name" value="{{ old('sender_name', $protocol->sender_name) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('sender_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sender_document" class="mb-1 block text-sm font-medium text-slate-700">CPF/CNPJ</label>
                    <input type="text" id="sender_document" name="sender_document" value="{{ old('sender_document', $protocol->sender_document) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('sender_document')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sender_email" class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" id="sender_email" name="sender_email" value="{{ old('sender_email', $protocol->sender_email) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('sender_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sender_phone" class="mb-1 block text-sm font-medium text-slate-700">Telefone</label>
                    <input type="text" id="sender_phone" name="sender_phone" value="{{ old('sender_phone', $protocol->sender_phone) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('sender_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('protocols.show', $protocol) }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Salvar alterações
            </button>
        </div>
    </form>
@endsection
