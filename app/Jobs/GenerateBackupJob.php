<?php

namespace App\Jobs;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gera um backup em background (fila database).
 *
 * Compatível com cPanel (processamento via `queue:work --stop-when-empty` no Cron)
 * e com VPS (Supervisor opcional).
 */
class GenerateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sem retentativa automática, evitando execução duplicada. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $backupId)
    {
    }

    public function handle(BackupService $service): void
    {
        $backup = Backup::find($this->backupId);

        if (! $backup || $backup->status === BackupStatus::CONCLUIDO) {
            return;
        }

        $service->generate($backup);
    }

    public function failed(\Throwable $e): void
    {
        Backup::whereKey($this->backupId)
            ->where('status', '!=', BackupStatus::CONCLUIDO->value)
            ->update([
                'status' => BackupStatus::FALHOU->value,
                'error' => 'Fila: '.$e->getMessage(),
                'finished_at' => now(),
            ]);
    }
}
