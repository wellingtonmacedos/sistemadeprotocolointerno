@extends('layouts.app')

@section('title', 'Central de Backup e Restauração')
@section('subtitle', 'Gere, baixe e restaure cópias de segurança')

@section('actions')
    <a href="{{ route('backups.index') }}"
       class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
        Atualizar situação
    </a>
    @if (auth()->user()->hasPermission('backups.settings'))
        <a href="{{ route('backups.settings') }}"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
            Configurações
        </a>
    @endif
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Backups armazenados" :value="$backups->total()" accent="indigo" />
        <x-stat-card label="Último backup"
                      :value="$lastBackup?->created_at?->format('d/m/Y H:i') ?? 'Nunca'"
                      :hint="$lastBackup?->filename" accent="emerald" />
        <x-stat-card label="Backup automático"
                      :value="$setting->enabled ? 'Ativado' : 'Desativado'"
                      :hint="$setting->enabled ? ucfirst($setting->frequency).' às '.$setting->run_at : null"
                      accent="{{ $setting->enabled ? 'sky' : 'slate' }}" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        @if (auth()->user()->hasPermission('backups.create'))
            <form method="POST" action="{{ route('backups.store') }}"
                  class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Gerar novo backup</h2>
                <p class="mt-1 text-xs text-slate-500">Gera um arquivo ZIP com o banco de dados e os anexos.</p>

                @if (auth()->user()->isSuperAdmin())
                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
                        <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <option value="platform">Backup da Plataforma (todas as Câmaras)</option>
                            <option value="institution">Backup de uma Câmara</option>
                        </select>
                    </div>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Câmara (quando aplicável)</label>
                        <select name="institution_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <option value="">Selecione…</option>
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="type" value="institution">
                @endif

                <button type="submit"
                        class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Gerar backup agora
                </button>
            </form>
        @endif

        @if (auth()->user()->hasPermission('backups.restore'))
            <form method="POST" action="{{ route('backups.restore.upload') }}" enctype="multipart/form-data"
                  onsubmit="return confirm('Restaurar um arquivo de backup substituirá os dados atuais. Continuar?')"
                  class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                @csrf
                <h2 class="text-sm font-semibold uppercase tracking-wide text-amber-700">Restaurar de arquivo</h2>
                <p class="mt-1 text-xs text-amber-700">
                    Envie um arquivo <code>.zip</code> gerado anteriormente por esta Central. A restauração sobrescreve o banco e os anexos.
                </p>

                <input type="file" name="file" accept=".zip,application/zip" required
                       class="mt-4 block w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-amber-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">

                <button type="submit"
                        class="mt-4 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Enviar e restaurar
                </button>
            </form>
        @endif
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico de backups</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tamanho</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($backups as $backup)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3">
                        <span class="font-medium text-slate-800">{{ $backup->filename }}</span>
                        @if ($backup->encrypted)
                            <span class="ml-2 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">AES-256-GCM</span>
                        @endif
                        @if ($backup->error)
                            <p class="text-xs text-red-500">{{ \Illuminate\Support\Str::limit($backup->error, 80) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $backup->type->label() }}
                        @if ($backup->institution)
                            <p class="text-xs text-slate-400">{{ $backup->institution->name }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $backup->humanSize() }}</td>
                    <td class="px-4 py-3"><x-badge :color="$backup->status->badge()">{{ $backup->status->label() }}</x-badge></td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $backup->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (in_array($backup->status->value, ['na_fila', 'falhou'], true) && auth()->user()->hasPermission('backups.create'))
                                <form method="POST" action="{{ route('backups.retry', $backup) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                        Reprocessar
                                    </button>
                                </form>
                            @endif
                            @if ($backup->status->value === 'concluido')
                                @if (auth()->user()->hasPermission('backups.download'))
                                    <a href="{{ route('backups.download', $backup) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Baixar
                                    </a>
                                @endif
                                @if (auth()->user()->hasPermission('backups.restore'))
                                    <form method="POST" action="{{ route('backups.restore', $backup) }}"
                                          onsubmit="return confirm('Restaurar este backup substituirá os dados atuais. Continuar?')">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                            Restaurar
                                        </button>
                                    </form>
                                @endif
                            @endif
                            @if (auth()->user()->hasPermission('backups.delete'))
                                <form method="POST" action="{{ route('backups.destroy', $backup) }}"
                                      onsubmit="return confirm('Excluir este backup?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                        Excluir
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum backup gerado ainda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $backups->links() }}</div>
@endsection
