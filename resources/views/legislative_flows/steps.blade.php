@extends('layouts.app')

@section('title', 'Etapas do fluxo')
@section('subtitle', $flow->modalityLabel().' · versão v'.$version->version)

@section('content')
    @if ($version->isPublished())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Esta versão está <strong>publicada</strong> e é imutável. Para alterar as etapas, crie uma nova versão.
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ol class="divide-y divide-slate-50">
                @forelse ($steps as $step)
                    <li class="flex items-start gap-3 px-6 py-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                            {{ $step->position }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ $step->name }}</p>
                            <p class="text-xs text-slate-500">{{ $step->typeLabel() }}</p>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-6 text-center text-sm text-slate-500">Nenhuma etapa configurada.</li>
                @endforelse
            </ol>
        </div>

        <div class="mt-4">
            <a href="{{ route('legislative-flows.show', $flow) }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Voltar
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('legislative-flows.steps.update', [$flow, $version]) }}">
            @csrf
            @method('PUT')

            @error('steps') <p class="mb-3 text-xs text-red-600">{{ $message }}</p> @enderror

            <div id="flow-steps" class="space-y-3">
                @foreach ($steps as $index => $step)
                    @include('legislative_flows._step_row', ['step' => $step, 'index' => $index])
                @endforeach
            </div>

            <template id="flow-step-template">
                @include('legislative_flows._step_row', ['step' => null, 'index' => '__INDEX__'])
            </template>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" data-add-step
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    + Adicionar etapa
                </button>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Salvar etapas
                </button>
                <a href="{{ route('legislative-flows.show', $flow) }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Voltar
                </a>
            </div>
        </form>

        <script>
            (function () {
                const container = document.getElementById('flow-steps');
                const template = document.getElementById('flow-step-template');
                if (!container || !template) return;

                let counter = 1000;

                function renumber() {
                    container.querySelectorAll('[data-step-row]').forEach(function (row, i) {
                        const label = row.querySelector('[data-step-number]');
                        if (label) label.textContent = i + 1;
                    });
                }

                document.querySelector('[data-add-step]').addEventListener('click', function () {
                    const html = template.innerHTML.replace(/__INDEX__/g, counter++);
                    const wrap = document.createElement('div');
                    wrap.innerHTML = html.trim();
                    container.appendChild(wrap.firstElementChild);
                    renumber();
                });

                container.addEventListener('click', function (e) {
                    const btn = e.target.closest('[data-remove-step]');
                    if (btn) {
                        btn.closest('[data-step-row]').remove();
                        renumber();
                    }
                });

                renumber();
            })();
        </script>
    @endif
@endsection
