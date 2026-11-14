<?php

namespace App\Http\Controllers;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Jobs\GenerateBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\Institution;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Central de backup e restauração (por Câmara e por Plataforma).
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups)
    {
    }

    public function index()
    {
        $user = auth()->user();

        $query = Backup::with(['creator', 'institution'])->latest();

        if (! $user->isSuperAdmin()) {
            $query->where(function ($q) {
                $q->where('institution_id', Tenant::id())->orWhereNull('institution_id');
            });
        }

        $backups = $query->paginate(15);

        $lastBackup = (clone $query)
            ->where('status', BackupStatus::CONCLUIDO)
            ->first();

        return view('backups.index', [
            'backups' => $backups,
            'setting' => BackupSetting::current(),
            'lastBackup' => $lastBackup,
            'institutions' => $user->isSuperAdmin() ? Institution::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => ['nullable', 'in:institution,platform'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $isSuperAdmin = auth()->user()->isSuperAdmin();
        $type = BackupType::INSTITUTION;

        if ($isSuperAdmin && $request->input('type') === 'platform') {
            $type = BackupType::PLATFORM;
        }

        $institution = match (true) {
            $type === BackupType::PLATFORM => null,
            $isSuperAdmin => Institution::find($request->input('institution_id')),
            default => Tenant::get(),
        };

        if ($type === BackupType::INSTITUTION && ! $institution) {
            return back()->with('error', 'Selecione uma Câmara para gerar o backup.');
        }

        $backup = $this->backups->create($type, $institution);

        AuditLogger::log('backup.queued', $backup, [], [], 'Backup enfileirado: '.$backup->filename);

        return back()->with('success', 'Backup enfileirado. A geração ocorre em segundo plano; atualize a página para acompanhar o andamento.');
    }

    /**
     * Reprocessa um backup que ficou na fila ou falhou.
     */
    public function retry(Backup $backup)
    {
        $this->authorizeBackup($backup);

        if ($backup->status === BackupStatus::CONCLUIDO) {
            return back()->with('error', 'Este backup já foi concluído.');
        }

        if ($backup->status === BackupStatus::EM_EXECUCAO) {
            return back()->with('error', 'Este backup já está em execução.');
        }

        GenerateBackupJob::dispatch($backup->id);

        AuditLogger::log('backup.retried', $backup, [], [], 'Backup reprocessado: '.$backup->filename);

        return back()->with('success', 'Backup reenviado para processamento.');
    }

    public function download(Backup $backup)
    {
        $this->authorizeBackup($backup);

        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            return back()->with('error', 'Arquivo de backup não encontrado.');
        }

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    public function restore(Request $request, Backup $backup)
    {
        $this->authorizeBackup($backup);

        try {
            $result = $this->backups->restore($backup, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha na restauração: '.$e->getMessage());
        }

        AuditLogger::log('backup.restored', $backup, [], [], 'Backup restaurado: '.$backup->filename);

        return back()->with('success', 'Backup restaurado com sucesso. Salvaguarda automática: '.$result['snapshot']->filename.'.');
    }

    public function uploadRestore(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        $path = $request->file('file')->store('backups/uploads', 'local');
        $absolute = Storage::disk('local')->path($path);

        // Restauração global (plataforma) é exclusiva do SUPER ADMIN.
        $institutionId = auth()->user()->isSuperAdmin() ? null : Tenant::id();

        try {
            $result = $this->backups->restoreFromUpload($absolute, $institutionId, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha na restauração: '.$e->getMessage());
        } finally {
            Storage::disk('local')->delete($path);
        }

        AuditLogger::log('backup.restored', null, [], [], 'Backup restaurado a partir de arquivo enviado.');

        return back()->with('success', 'Backup restaurado com sucesso. Salvaguarda automática: '.$result['snapshot']->filename.'.');
    }

    public function destroy(Backup $backup)
    {
        $this->authorizeBackup($backup);

        $name = $backup->filename;
        $this->backups->deleteBackup($backup);

        AuditLogger::log('backup.deleted', null, [], [], 'Backup excluído: '.$name);

        return back()->with('success', 'Backup excluído com sucesso.');
    }

    public function settings()
    {
        return view('backups.settings', [
            'setting' => BackupSetting::current(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'run_at' => ['required', 'date_format:H:i'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'destination' => ['required', 'in:local'],
            'encrypt' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['encrypt'] = $request->boolean('encrypt');

        $setting = BackupSetting::current();
        $before = $setting->only(array_keys($data));
        $setting->update($data);

        AuditLogger::log('backup.settings_updated', $setting, $before, $data, 'Configurações de backup atualizadas.');

        return back()->with('success', 'Configurações de backup atualizadas.');
    }

    /** Impede acesso a backups de outras Câmaras. */
    private function authorizeBackup(Backup $backup): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($backup->institution_id !== null && $backup->institution_id !== Tenant::id()) {
            abort(403);
        }
    }
}
