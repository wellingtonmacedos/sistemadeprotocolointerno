@props(['label', 'value', 'hint' => null, 'accent' => 'indigo'])

@php
    $accents = [
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'red' => 'bg-red-50 text-red-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold text-slate-800">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
            @endif
        </div>
        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $accents[$accent] ?? $accents['indigo'] }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/>
            </svg>
        </span>
    </div>
</div>
