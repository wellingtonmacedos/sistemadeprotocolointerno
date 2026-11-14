@extends('layouts.app')

@section('title', 'Configurações de Backup')
@section('subtitle', 'Defina a rotina automática e a política de retenção')

@section('actions')
    <a href="{{ route('backups.index') }}"
       class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
        Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('backups.settings.update') }}"
          class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
                <p class="text-sm font-semibold text-slate-800">Backup automático</p>
                <p class="text-xs text-slate-500">Gera backups periódicos via agendador (Cron).</p>
            </div>
            <label class="inline-flex cursor-pointer items-center">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($setting->enabled)
                       class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            </label>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Frequência</label>
                <select name="frequency" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="daily" @selected($setting->frequency === 'daily')>Diário</option>
                    <option value="weekly" @selected($setting->frequency === 'weekly')>Semanal (segunda-feira)</option>
                    <option value="monthly" @selected($setting->frequency === 'monthly')>Mensal (dia 1)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Horário de execução</label>
                <input type="time" name="run_at" value="{{ old('run_at', $setting->run_at) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Retenção (dias)</label>
                <input type="number" name="retention_days" min="1" max="3650" value="{{ old('retention_days', $setting->retention_days) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Máximo de cópias</label>
                <input type="number" name="max_copies" min="1" max="1000" value="{{ old('max_copies', $setting->max_copies) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Destino</label>
                <select name="destination" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="local" @selected($setting->destination === 'local')>Armazenamento local do servidor</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="encrypt" value="0">
                    <input type="checkbox" name="encrypt" value="1" @checked($setting->encrypt)
                           class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Criptografar arquivos de backup
                </label>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-xs text-slate-500">
            <p class="font-semibold text-slate-600">Agendamento</p>
            <p class="mt-1">
                Adicione ao Cron do servidor (cPanel ou VPS):
                <code class="mt-1 block rounded bg-white px-2 py-1 text-slate-700">* * * * * php {{ base_path('artisan') }} schedule:run >> /dev/null 2>&1</code>
            </p>
            <p class="mt-3 font-semibold text-slate-600">Processamento da fila</p>
            <p class="mt-1">
                Os backups são gerados em segundo plano. No cPanel, o agendador acima já processa a fila
                (<code>queue:work --stop-when-empty</code>) a cada minuto, sem necessidade de Supervisor.
                Em VPS, opcionalmente utilize Supervisor com <code>php artisan queue:work</code>.
            </p>
            <p class="mt-3 font-semibold text-slate-600">Criptografia</p>
            <p class="mt-1">
                Quando habilitada, o arquivo é protegido com AES-256-GCM usando a <code>APP_KEY</code> do ambiente.
                A chave deve ser preservada para permitir a restauração.
            </p>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Salvar configurações
            </button>
        </div>
    </form>
@endsection
