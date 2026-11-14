<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\BackupService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Executa os backups automáticos configurados por Câmara.
 *
 * Deve ser agendado de hora em hora (via Scheduler); a decisão de gerar ou não
 * o backup é feita conforme a frequência e o horário configurados.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--force : Ignora a verificação de frequência/horário}';

    protected $description = 'Gera os backups automáticos configurados na Central de Backup.';

    public function handle(BackupService $service): int
    {
        $now = now();
        $count = 0;

        Tenant::withoutScope(function () use ($service, $now, &$count) {
            BackupSetting::with('institution')->where('enabled', true)->get()
                ->each(function (BackupSetting $setting) use ($service, $now, &$count) {
                    if (! $this->option('force') && ! $this->isDue($setting, $now)) {
                        return;
                    }

                    $institution = $setting->institution;

                    $backup = $service->create(
                        $institution ? BackupType::INSTITUTION : BackupType::PLATFORM,
                        $institution
                    );

                    $service->pruneFor($setting);

                    $count++;

                    $this->line(sprintf('Backup enfileirado: %s', $backup->filename));
                });
        });

        $this->info("Backups executados: {$count}");

        return self::SUCCESS;
    }

    protected function isDue(BackupSetting $setting, Carbon $now): bool
    {
        $hour = Carbon::createFromFormat('H:i', substr((string) $setting->run_at, 0, 5))->hour;

        if ((int) $now->hour !== $hour) {
            return false;
        }

        $matchesFrequency = match ($setting->frequency) {
            'weekly' => $now->isMonday(),
            'monthly' => $now->day === 1,
            default => true,
        };

        if (! $matchesFrequency) {
            return false;
        }

        // Evita duplicidade caso o comando seja executado mais de uma vez.
        return ! Backup::whereIn('status', [
            BackupStatus::NA_FILA,
            BackupStatus::EM_EXECUCAO,
            BackupStatus::CONCLUIDO,
        ])
            ->when($setting->institution_id, fn ($q) => $q->where('institution_id', $setting->institution_id))
            ->when(! $setting->institution_id, fn ($q) => $q->whereNull('institution_id'))
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->exists();
    }
}
