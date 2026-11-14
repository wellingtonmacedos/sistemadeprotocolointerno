@extends('layouts.app')

@section('title', 'Relatório de Protocolos')
@section('subtitle', 'Listagem detalhada com filtros por período e situação')

@section('actions')
    <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
        Voltar aos relatórios
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
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
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Situação</label>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todas</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
            <select name="document_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todos</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['document_type'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Setor</label>
            <select name="sector_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todos</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? null) == $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('reports.protocols') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        </div>
    </form>

    <div class="mb-3 text-sm text-slate-500">
        <strong class="text-slate-700">{{ $total }}</strong> protocolo(s) encontrado(s).
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Número</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assunto</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Setor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Protocolado em</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($protocols as $protocol)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('protocols.show', $protocol) }}" class="font-medium text-indigo-600 hover:underline">
                            {{ $protocol->formatted_number }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($protocol->subject, 50) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $protocol->document_type->label() }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $protocol->destinationSector?->name ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge :color="$protocol->status->badge()">{{ $protocol->status->label() }}</x-badge></td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum protocolo encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $protocols->links() }}</div>
@endsection
