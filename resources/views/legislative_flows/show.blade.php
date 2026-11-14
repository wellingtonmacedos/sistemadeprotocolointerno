@extends('layouts.app')

@section('title', 'Fluxo: '.$flow->name)
@section('subtitle', $flow->modalityLabel())

@section('actions')
    <a href="{{ route('legislative-flows.index') }}"
       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Voltar
    </a>
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge color="bg-indigo-50 text-indigo-700">{{ $flow->modalityLabel() }}</x-badge>
                    @if ($current)
                        <x-badge color="bg-emerald-100 text-emerald-700">v{{ $current->version }} vigente</x-badge>
                    @endif
                    @if ($draft)
                        <x-badge color="bg-amber-100 text-amber-700">v{{ $draft->version }} rascunho</x-badge>
                    @endif
                </div>
                @if ($flow->description)
                    <p class="mt-3 text-sm text-slate-600">{{ $flow->description }}</p>
                @endif

                @if ($version)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('legislative-flows.steps.edit', [$flow, $version]) }}"
                           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            {{ $version->isPublished() ? 'Ver etapas (v'.$version->version.')' : 'Editar etapas (v'.$version->version.')' }}
                        </a>
                        @if (! $version->isPublished() && auth()->user()->hasPermission('legislative_flows.manage'))
                            <form method="POST" action="{{ route('legislative-flows.publish', [$flow, $version]) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Publicar v{{ $version->version }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            @if ($version)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <h2 class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-800">
                        Etapas da versão v{{ $version->version }}
                    </h2>
                    <ol class="divide-y divide-slate-50">
                        @forelse ($steps as $step)
                            <li class="flex items-start gap-3 px-6 py-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                    {{ $step->position }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800">{{ $step->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $step->typeLabel() }}
                                        @if ($step->sector) · Setor: {{ $step->sector->name }} @endif
                                        @if ($step->commission) · Comissão: {{ $step->commission->acronym }} @endif
                                        @if ($step->deadline_days) · Prazo: {{ $step->deadline_days }} dia(s) @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap justify-end gap-1">
                                    @if ($step->is_required)
                                        <x-badge color="bg-rose-50 text-rose-700">Obrigatória</x-badge>
                                    @else
                                        <x-badge color="bg-slate-100 text-slate-600">Opcional</x-badge>
                                    @endif
                                    @if ($step->can_skip)
                                        <x-badge color="bg-amber-50 text-amber-700">Pode pular</x-badge>
                                    @endif
                                    @if ($step->requires_document)
                                        <x-badge color="bg-sky-50 text-sky-700">Exige documento</x-badge>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-center text-sm text-slate-500">Nenhuma etapa configurada.</li>
                        @endforelse
                    </ol>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-800">Versões</h2>
                <ul class="divide-y divide-slate-50">
                    @foreach ($flow->versions as $v)
                        <li class="flex items-center justify-between px-6 py-3 text-sm">
                            <div>
                                <p class="font-medium text-slate-800">v{{ $v->version }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ ucfirst($v->status) }} · {{ $v->steps_count }} etapa(s)
                                    @if ($v->published_at) · {{ $v->published_at->format('d/m/Y H:i') }} @endif
                                </p>
                            </div>
                            <a href="{{ route('legislative-flows.steps.edit', [$flow, $v]) }}"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-700">ver</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (auth()->user()->hasPermission('legislative_flows.manage'))
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-slate-600">
                        Alterações em um fluxo publicado exigem uma nova versão. As proposições em andamento
                        mantêm a versão vigente no momento de sua instauração.
                    </p>
                    <form method="POST" action="{{ route('legislative-flows.versions.store', $flow) }}" class="mt-3">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Criar nova versão (rascunho)
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
