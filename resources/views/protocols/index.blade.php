@extends('layouts.app')

@section('title', 'Protocolos')
@section('subtitle', 'Consulta e acompanhamento dos protocolos')

@section('actions')
    @if (auth()->user()->hasPermission('protocols.receive') || auth()->user()->hasPermission('protocols.receive_commission'))
        <a href="{{ route('protocols.inbox') }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Caixa de entrada
        </a>
    @endif
    @if (auth()->user()->hasPermission('protocols.create'))
        <a href="{{ route('protocols.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Novo protocolo
        </a>
    @endif
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('protocols.index') }}"
              class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <div class="lg:col-span-2 xl:col-span-2">
                <label for="q" class="mb-1 block text-xs font-medium text-slate-500">Busca</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}"
                       placeholder="Número, assunto ou interessado"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs font-medium text-slate-500">Situação</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="priority" class="mb-1 block text-xs font-medium text-slate-500">Prioridade</label>
                <select id="priority" name="priority" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="document_type" class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
                <select id="document_type" name="document_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('document_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="sector_id" class="mb-1 block text-xs font-medium text-slate-500">Setor destino</label>
                <select id="sector_id" name="sector_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected((string) request('sector_id') === (string) $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from" class="mb-1 block text-xs font-medium text-slate-500">De</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="to" class="mb-1 block text-xs font-medium text-slate-500">Até</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('protocols.index') }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Número</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assunto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Interessado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Setor destino</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prioridade</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($protocols as $protocol)
                        <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('protocols.show', $protocol) }}"
                                   class="font-medium text-indigo-600 hover:underline">
                                    {{ $protocol->formatted_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $protocol->document_type->label() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($protocol->subject, 50) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $protocol->sender_name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $protocol->destinationSector?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :color="$protocol->priority->badge()">{{ $protocol->priority->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :color="$protocol->status->badge()">{{ $protocol->status->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-400">Nenhum protocolo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $protocols->links() }}
    </div>
@endsection
