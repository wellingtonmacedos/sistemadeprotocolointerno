@extends('layouts.app')

@section('title', 'Minhas Comissões')
@section('subtitle', 'Comissões em que você atua e as pendências sob sua responsabilidade')

@section('content')
    <div class="space-y-6">
        {{-- Comissões do usuário --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Minhas comissões</h3>
                <span class="text-xs text-slate-400">{{ $commissions->count() }} comissão(ões)</span>
            </div>

            @if ($commissions->isEmpty())
                <p class="py-8 text-center text-sm text-slate-400">
                    Você não integra nenhuma comissão atualmente.
                </p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($commissions as $commission)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $commission->acronym ? $commission->acronym.' — ' : '' }}{{ $commission->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Seu papel:
                                        {{ $memberships[$commission->id]->map(fn ($m) => \App\Enums\CommissionMemberRole::tryFrom($m->role)?->label() ?? $m->role)->implode(', ') }}
                                    </p>
                                </div>
                                <a href="{{ route('commissions.show', $commission) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Ver comissão
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Pendências de parecer e recebimento --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Pendências de parecer e recebimento</h3>
                <span class="text-xs text-slate-400">{{ $pendingForwardings->count() }} registro(s)</span>
            </div>

            @if ($pendingForwardings->isEmpty())
                <p class="py-8 text-center text-sm text-slate-400">Nenhuma pendência nas suas comissões.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Proposição</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Comissão</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prazo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingForwardings as $forwarding)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $forwarding->proposition?->official_number ?? $forwarding->proposition?->title ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $forwarding->commission?->acronym ?? $forwarding->commission?->name }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $forwarding->deadline?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$forwarding->status->badge()">{{ $forwarding->status->label() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($forwarding->proposition && auth()->user()->hasPermission('propositions.view'))
                                        <a href="{{ route('propositions.show', $forwarding->proposition) }}"
                                           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Abrir
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
