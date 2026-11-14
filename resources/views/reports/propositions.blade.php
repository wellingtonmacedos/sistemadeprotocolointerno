@extends('layouts.app')

@section('title', 'Relatório de Proposições')
@section('subtitle', 'Listagem detalhada com filtros por período e modalidade')

@section('actions')
    <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
        Voltar aos relatórios
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
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
            <label class="mb-1 block text-xs font-medium text-slate-500">Modalidade</label>
            <select name="modality" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todas</option>
                @foreach ($modalities as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['modality'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
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
            <label class="mb-1 block text-xs font-medium text-slate-500">Autor</label>
            <select name="author_councilor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todos</option>
                @foreach ($councilors as $councilor)
                    <option value="{{ $councilor->id }}" @selected((string) ($filters['author_councilor_id'] ?? '') === (string) $councilor->id)>{{ $councilor->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Comissão</label>
            <select name="commission_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todas</option>
                @foreach ($commissions as $commission)
                    <option value="{{ $commission->id }}" @selected((string) ($filters['commission_id'] ?? '') === (string) $commission->id)>{{ $commission->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Sessão</label>
            <select name="session_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todas</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}" @selected((string) ($filters['session_id'] ?? '') === (string) $session->id)>
                        {{ $session->type?->label() }} nº {{ $session->number }} — {{ $session->date?->format('d/m/Y') }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Etapa</label>
            <select name="stage" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">Todas</option>
                @foreach ($stages as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['stage'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('reports.propositions') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        </div>
    </form>

    <div class="mb-3 text-sm text-slate-500">
        <strong class="text-slate-700">{{ $total }}</strong> proposição(ões) encontrada(s).
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Identificação</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Título</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Autor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Criada em</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($propositions as $proposition)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('propositions.show', $proposition) }}" class="font-medium text-indigo-600 hover:underline">
                            {{ $proposition->official_number ?? $proposition->modality->abbreviation().' '.$proposition->number.'/'.$proposition->year }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($proposition->title, 50) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $proposition->authorName() }}</td>
                    <td class="px-4 py-3"><x-badge :color="$proposition->status->badge()">{{ $proposition->status->label() }}</x-badge></td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $proposition->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Nenhuma proposição encontrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $propositions->links() }}</div>
@endsection
