@if (session('success'))
    <div class="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <span class="font-medium">Sucesso:</span> <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <span class="font-medium">Erro:</span> <span>{{ session('error') }}</span>
    </div>
@endif

@if (session('warning'))
    <div class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <span class="font-medium">Atenção:</span> <span>{{ session('warning') }}</span>
    </div>
@endif
