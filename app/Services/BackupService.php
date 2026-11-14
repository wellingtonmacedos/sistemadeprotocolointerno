<?php

namespace App\Services;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Jobs\GenerateBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\Councilor;
use App\Models\Institution;
use App\Models\Proposition;
use App\Models\PropositionAttachment;
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\Sector;
use App\Models\User;
use App\Support\BackupEncrypter;
use App\Support\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Central de backup e restauração.
 *
 * Regras de negócio permanecem independentes do ambiente: as diferenças entre
 * cPanel (PHP nativo) e VPS (ferramentas de linha de comando) ficam isoladas
 * nos adaptadores de dump do banco (SQLite / MySQL).
 *
 * A geração é assíncrona (fila `database`) e a restauração é protegida por
 * salvaguarda automática (snapshot) e recuperação em caso de falha.
 */
class BackupService
{
    /** Versão de manifesto gerada atualmente. */
    public const FORMAT_VERSION = 2;

    /** Maior versão de manifesto aceita na restauração (compatibilidade). */
    public const FORMAT_VERSION_SUPPORTED = 2;

    /**
     * Tabelas operacionais do subsistema de backup.
     *
     * Não participam da restauração de dados por instituição: revertê-las
     * apagaria/duplicaria o histórico de backups e deixaria o registro da
     * salvaguarda em estado inconsistente.
     */
    protected const OPERATIONAL_TABLES = ['backups', 'backup_settings'];

    /**
     * Enfileira a geração de um novo backup.
     *
     * Cria apenas o registro (status "na_fila") e delega a execução ao Job,
     * evitando bloquear a requisição HTTP em bases grandes.
     */
    public function create(BackupType $type, ?Institution $institution = null, ?int $userId = null): Backup
    {
        $backup = $this->newRecord($type, $institution, $userId);

        GenerateBackupJob::dispatch($backup->id);

        return $backup;
    }

    /**
     * Executa efetivamente a geração do arquivo de backup (chamado pelo Job).
     *
     * O registro é "reivindicado" atomicamente, impedindo execução duplicada
     * pelo mesmo backup (dois workers não processam o mesmo ID).
     */
    public function generate(Backup $backup, bool $isSnapshot = false): Backup
    {
        $claimed = Backup::whereKey($backup->id)
            ->whereIn('status', [BackupStatus::NA_FILA->value, BackupStatus::FALHOU->value])
            ->update([
                'status' => BackupStatus::EM_EXECUCAO->value,
                'started_at' => now(),
                'error' => null,
            ]);

        $backup->refresh();

        if (! $claimed) {
            // Já concluído ou em execução por outro worker.
            return $backup;
        }

        $setting = $this->settingFor($backup->institution_id);
        $encrypt = ! $isSnapshot && (bool) $setting?->encrypt;

        $workDir = storage_path('app/backup-tmp-'.uniqid());

        try {
            Storage::disk('local')->makeDirectory(dirname($backup->path));
            @mkdir($workDir, 0775, true);

            $absolute = Storage::disk('local')->path($backup->path);

            $database = $this->dumpDatabase($workDir);
            $files = array_values(array_filter(
                $this->collectFiles($backup->institution_id),
                fn ($file) => Storage::disk('local')->exists($file)
            ));

            $manifest = [
                'version' => self::FORMAT_VERSION,
                'type' => $backup->type->value,
                'snapshot' => $isSnapshot,
                'encrypted' => $encrypt,
                'generated_at' => now()->toIso8601String(),
                'application' => [
                    'name' => config('app.name'),
                    'laravel' => app()->version(),
                    'php' => PHP_VERSION,
                ],
                'institution' => $backup->institution_id ? [
                    'id' => $backup->institution_id,
                    'name' => $backup->institution?->name,
                ] : null,
                'database' => $database,
                'counts' => $this->counts($backup->institution_id),
                'files' => array_map(fn ($f) => ['path' => $f, 'size' => $this->fileSize($f)], $files),
            ];

            file_put_contents(
                $workDir.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $zip = new ZipArchive();
            if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Não foi possível criar o arquivo ZIP de backup.');
            }

            $zip->addFile($workDir.'/'.$database['file'], $database['file']);
            $zip->addFile($workDir.'/manifest.json', 'manifest.json');

            foreach ($files as $filePath) {
                $zip->addFile(Storage::disk('local')->path($filePath), 'files/'.$filePath);
            }

            $zip->close();

            if ($encrypt) {
                $encryptedPath = $absolute.'.enc';
                BackupEncrypter::encryptFile($absolute, $encryptedPath);
                @unlink($absolute);
                rename($encryptedPath, $absolute);
            }

            $backup->update([
                'status' => BackupStatus::CONCLUIDO,
                'encrypted' => $encrypt,
                'size' => filesize($absolute) ?: null,
                'sha256' => hash_file('sha256', $absolute),
                'manifest' => $manifest,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $absolute = Storage::disk('local')->path($backup->path);
            if (is_file($absolute)) {
                @unlink($absolute);
            }

            $backup->update([
                'status' => BackupStatus::FALHOU,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        } finally {
            $this->deleteDirectory($workDir);
        }

        return $backup->refresh();
    }

    // ---------------------------------------------------------------------
    // Restauração com salvaguardas (A3)
    // ---------------------------------------------------------------------

    /**
     * Restaura um backup previamente gerado, com salvaguarda automática.
     *
     * @return array{snapshot: Backup, institution_id: ?int}
     */
    public function restore(Backup $backup, ?int $actorId = null): array
    {
        if ($backup->status !== BackupStatus::CONCLUIDO) {
            throw new \RuntimeException('Somente backups concluídos podem ser restaurados.');
        }

        $absolute = Storage::disk($backup->disk)->path($backup->path);

        if (! is_file($absolute)) {
            throw new \RuntimeException('Arquivo de backup não encontrado no armazenamento.');
        }

        return $this->performRestore($absolute, $backup->institution_id, $backup->sha256, $actorId);
    }

    /**
     * Restaura a partir de um arquivo ZIP enviado manualmente.
     *
     * @return array{snapshot: Backup, institution_id: ?int}
     */
    public function restoreFromUpload(string $absolutePath, ?int $institutionId = null, ?int $actorId = null): array
    {
        return $this->performRestore($absolutePath, $institutionId, null, $actorId);
    }

    /**
     * @return array{snapshot: Backup, institution_id: ?int}
     */
    protected function performRestore(string $archivePath, ?int $institutionId, ?string $expectedSha, ?int $actorId): array
    {
        $lock = Cache::lock('backup:restore', 900);

        if (! $lock->get()) {
            throw new \RuntimeException('Outra restauração está em andamento. Tente novamente em instantes.');
        }

        $tempPlain = null;
        $maintenance = false;

        try {
            if (! is_readable($archivePath)) {
                throw new \RuntimeException('Arquivo de backup ilegível.');
            }

            // ETAPA 1 — preparação: hash, criptografia, manifesto, versão.
            if ($expectedSha && hash_file('sha256', $archivePath) !== $expectedSha) {
                throw new \RuntimeException('Falha na verificação de integridade do backup (hash divergente).');
            }

            $source = $archivePath;
            if (BackupEncrypter::isEncryptedFile($archivePath)) {
                $tempPlain = $archivePath.'.plain';
                BackupEncrypter::decryptFile($archivePath, $tempPlain);
                $source = $tempPlain;
            }

            $manifest = $this->readManifest($source);
            $this->validateManifest($manifest, $institutionId, $archivePath);

            // ETAPA 2 — salvaguarda: snapshot obrigatório do estado atual.
            $snapshot = $this->createSnapshot($institutionId, $actorId);

            if ($snapshot->status !== BackupStatus::CONCLUIDO) {
                throw new \RuntimeException(
                    'Salvaguarda automática falhou; a restauração foi abortada. '.$snapshot->error
                );
            }

            // ETAPA 3 — restauração controlada.
            $maintenance = $this->enterMaintenance();

            try {
                $this->applyArchive($source, $institutionId);
                $this->verifyIntegrity($institutionId);
            } catch (\Throwable $e) {
                // ETAPA 4 — recuperação do snapshot anterior.
                $this->recoverFromSnapshot($snapshot, $institutionId);

                AuditLogger::log('backup.restore_failed', null, [], [
                    'error' => $e->getMessage(),
                    'snapshot' => $snapshot->filename,
                ], 'Falha na restauração; estado anterior recuperado.');

                throw new \RuntimeException(
                    'A restauração falhou e o estado anterior foi recuperado automaticamente: '.$e->getMessage(),
                    previous: $e
                );
            }

            return ['snapshot' => $snapshot, 'institution_id' => $institutionId];
        } finally {
            if ($maintenance) {
                $this->leaveMaintenance();
            }

            if ($tempPlain && is_file($tempPlain)) {
                @unlink($tempPlain);
            }

            $lock->release();
        }
    }

    /**
     * ETAPA 1 — valida o manifesto e a compatibilidade do pacote.
     */
    protected function validateManifest(array $manifest, ?int $institutionId, string $archivePath): void
    {
        $version = (int) ($manifest['version'] ?? 1);

        if ($version > self::FORMAT_VERSION_SUPPORTED) {
            throw new \RuntimeException("Versão de backup não suportada ({$version}). Atualize o sistema.");
        }

        $manifestInstitution = $manifest['institution']['id'] ?? null;

        // Isolamento: um backup de Câmara só pode restaurar a própria Câmara.
        if ($institutionId !== null && (int) $manifestInstitution !== $institutionId) {
            throw new \RuntimeException('O backup pertence a outra Câmara; restauração cruzada não permitida.');
        }

        // Espaço em disco (melhor esforço).
        $free = @disk_free_space(dirname($archivePath));
        $size = @filesize($archivePath);

        if ($free !== false && $size !== false && $free < $size * 3) {
            throw new \RuntimeException('Espaço em disco insuficiente para realizar a restauração com segurança.');
        }

        // Integridade dos arquivos declarados no manifesto.
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Arquivo de backup inválido ou corrompido.');
        }

        foreach ($manifest['files'] ?? [] as $file) {
            if ($zip->locateName('files/'.$file['path']) === false) {
                $zip->close();
                throw new \RuntimeException('Backup incompleto: arquivo ausente ('.$file['path'].').');
            }
        }

        $zip->close();
    }

    /**
     * ETAPA 2 — gera um snapshot síncrono do estado atual (nunca criptografado).
     */
    protected function createSnapshot(?int $institutionId, ?int $actorId): Backup
    {
        $type = $institutionId ? BackupType::INSTITUTION : BackupType::PLATFORM;
        $institution = $institutionId ? Institution::find($institutionId) : null;

        $backup = $this->newRecord($type, $institution, $actorId, snapshot: true);

        return $this->generate($backup, isSnapshot: true);
    }

    /**
     * ETAPA 4 — recupera o estado anterior a partir do snapshot.
     */
    protected function recoverFromSnapshot(Backup $snapshot, ?int $institutionId): void
    {
        $absolute = Storage::disk($snapshot->disk)->path($snapshot->path);

        if (! is_file($absolute)) {
            throw new \RuntimeException('Snapshot de recuperação não encontrado; intervenção manual necessária.');
        }

        if ($snapshot->sha256 && hash_file('sha256', $absolute) !== $snapshot->sha256) {
            throw new \RuntimeException('Snapshot de recuperação corrompido; intervenção manual necessária.');
        }

        $this->applyArchive($absolute, $institutionId);
    }

    /**
     * ETAPA 3 — aplica o conteúdo do arquivo (banco + documentos).
     */
    protected function applyArchive(string $archivePath, ?int $institutionId): void
    {
        $workDir = storage_path('app/restore-'.uniqid());

        @mkdir($workDir, 0775, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($archivePath) !== true) {
                throw new \RuntimeException('Arquivo de backup inválido ou corrompido.');
            }
            $zip->extractTo($workDir);
            $zip->close();

            if ($institutionId === null) {
                $this->restoreFullDatabase($workDir);
            } else {
                $this->restoreInstitutionDatabase($workDir, $institutionId);
            }

            $filesDir = $workDir.'/files';
            if (is_dir($filesDir)) {
                $this->copyDirectory($filesDir, Storage::disk('local')->path(''));
            }
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * Validação pós-restauração (ETAPA 3).
     */
    protected function verifyIntegrity(?int $institutionId): void
    {
        DB::connection()->getPdo();

        foreach (['institutions', 'users', 'protocols', 'propositions'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("Integridade comprometida: tabela ausente ({$table}).");
            }
        }

        if ($institutionId !== null && ! DB::table('institutions')->where('id', $institutionId)->exists()) {
            throw new \RuntimeException('Integridade comprometida: instituição restaurada não encontrada.');
        }

        DB::table('protocols')->count();
        DB::table('propositions')->count();
    }

    // ---------------------------------------------------------------------
    // Modo de manutenção controlado
    // ---------------------------------------------------------------------

    protected function enterMaintenance(): bool
    {
        try {
            Artisan::call('down');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function leaveMaintenance(): void
    {
        try {
            Artisan::call('up');
        } catch (\Throwable) {
            // Nunca deixar o sistema preso em manutenção por falha no comando.
        }
    }

    /**
     * Aplica a política de retenção da configuração informada.
     */
    public function pruneFor(BackupSetting $setting): int
    {
        $removed = 0;

        $base = fn () => Backup::where('status', BackupStatus::CONCLUIDO)
            ->when($setting->institution_id, fn ($q) => $q->where('institution_id', $setting->institution_id))
            ->when(! $setting->institution_id, fn ($q) => $q->whereNull('institution_id'));

        // Retenção por idade.
        foreach ($base()->where('created_at', '<', now()->subDays((int) $setting->retention_days))->get() as $backup) {
            $this->deleteBackup($backup);
            $removed++;
        }

        // Retenção por quantidade de cópias.
        $expired = $base()->orderByDesc('created_at')->get()->slice(max(1, (int) $setting->max_copies));

        foreach ($expired as $backup) {
            if ($backup->exists) {
                $this->deleteBackup($backup);
                $removed++;
            }
        }

        return $removed;
    }

    public function deleteBackup(Backup $backup): void
    {
        if ($backup->path && Storage::disk($backup->disk)->exists($backup->path)) {
            Storage::disk($backup->disk)->delete($backup->path);
        }

        $backup->delete();
    }

    // ---------------------------------------------------------------------
    // Criação de registro
    // ---------------------------------------------------------------------

    protected function newRecord(
        BackupType $type,
        ?Institution $institution,
        ?int $userId,
        bool $snapshot = false,
    ): Backup {
        $institutionId = $type === BackupType::INSTITUTION ? $institution?->id : null;

        $filename = sprintf(
            '%s-%s-%s-%s.zip',
            $snapshot ? 'snapshot' : 'backup',
            $type->value,
            $institutionId ? 'camara-'.$institutionId : 'plataforma',
            now()->format('Ymd-His').'-'.substr(md5(uniqid('', true)), 0, 4)
        );

        $directory = trim((string) config('protocolo.backups_path', 'backups'), '/');

        return Backup::create([
            'institution_id' => $institutionId,
            'type' => $type,
            'filename' => $filename,
            'disk' => 'local',
            'path' => $directory.'/'.$filename,
            'status' => BackupStatus::NA_FILA,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    protected function settingFor(?int $institutionId): ?BackupSetting
    {
        return BackupSetting::where('scope_key', BackupSetting::scopeKeyFor($institutionId))->first();
    }

    // ---------------------------------------------------------------------
    // Adaptadores de banco de dados (cPanel / VPS)
    // ---------------------------------------------------------------------

    /**
     * @return array{driver:string,file:string,bytes:int}
     */
    protected function dumpDatabase(string $workDir): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}", []);

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($connection, $workDir),
            'mysql', 'mariadb' => $this->dumpMysql($connection, $workDir),
            default => throw new \RuntimeException("Driver de banco não suportado para backup: {$driver}."),
        };
    }

    protected function dumpSqlite(array $connection, string $workDir): array
    {
        $source = $connection['database'] ?? database_path('database.sqlite');

        if (! is_file($source)) {
            throw new \RuntimeException('Arquivo do banco SQLite não encontrado: '.$source);
        }

        $target = $workDir.'/database.sqlite';
        copy($source, $target);

        return ['driver' => 'sqlite', 'file' => 'database.sqlite', 'bytes' => filesize($target)];
    }

    protected function dumpMysql(array $connection, string $workDir): array
    {
        if (! $this->binaryExists('mysqldump')) {
            throw new \RuntimeException('Utilitário "mysqldump" não disponível neste ambiente.');
        }

        $command = [
            'mysqldump',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? ''),
            '--single-transaction',
            '--routines',
            '--skip-comments',
            $connection['database'] ?? '',
        ];

        $process = new Process($command);
        $process->setTimeout(1200);

        if (! empty($connection['password'])) {
            $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Falha ao executar mysqldump: '.trim($process->getErrorOutput()));
        }

        $target = $workDir.'/database.sql';
        file_put_contents($target, $process->getOutput());

        return ['driver' => 'mysql', 'file' => 'database.sql', 'bytes' => filesize($target)];
    }

    protected function binaryExists(string $binary): bool
    {
        $probe = PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary];

        try {
            $process = new Process($probe);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Restauração — banco de dados
    // ---------------------------------------------------------------------

    protected function restoreFullDatabase(string $workDir): void
    {
        if (is_file($workDir.'/database.sqlite')) {
            $target = config('database.connections.sqlite.database') ?: database_path('database.sqlite');

            if (! is_file($target)) {
                throw new \RuntimeException('Banco SQLite de destino não encontrado.');
            }

            DB::disconnect();
            copy($workDir.'/database.sqlite', $target);
            DB::reconnect();
        } elseif (is_file($workDir.'/database.sql')) {
            $this->restoreMysql($workDir.'/database.sql');
        } else {
            throw new \RuntimeException('Backup não contém arquivo de banco de dados.');
        }
    }

    /**
     * Restauração individual: aplica apenas as linhas da Câmara informada,
     * preservando integralmente os dados das demais Câmaras.
     */
    protected function restoreInstitutionDatabase(string $workDir, int $institutionId): void
    {
        if (is_file($workDir.'/database.sqlite')) {
            $this->restoreSqliteInstitution($workDir.'/database.sqlite', $institutionId);
        } elseif (is_file($workDir.'/database.sql')) {
            $this->restoreMysqlInstitution($workDir.'/database.sql', $institutionId);
        } else {
            throw new \RuntimeException('Backup não contém arquivo de banco de dados.');
        }
    }

    protected function restoreSqliteInstitution(string $snapshotFile, int $institutionId): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('ATTACH DATABASE '.$pdo->quote($snapshotFile).' AS snapshot');

        try {
            $snapshotTables = $pdo->query(
                "SELECT name FROM snapshot.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            )->fetchAll(\PDO::FETCH_COLUMN);

            DB::beginTransaction();

            foreach ($this->tenantTables($pdo, 'main') as $table) {
                if (! in_array($table, $snapshotTables, true)) {
                    continue;
                }

                $columns = $this->sharedColumns($pdo, $table);
                if ($columns === []) {
                    continue;
                }

                $columnList = implode(', ', array_map(fn ($c) => '"'.$c.'"', $columns));

                DB::statement("DELETE FROM main.\"{$table}\" WHERE institution_id = ?", [$institutionId]);
                DB::statement(
                    "INSERT INTO main.\"{$table}\" ({$columnList}) SELECT {$columnList} FROM snapshot.\"{$table}\" WHERE institution_id = ?",
                    [$institutionId]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $pdo->exec('DETACH DATABASE snapshot');
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** @return array<int,string> */
    protected function tenantTables(\PDO $pdo, string $schema): array
    {
        $tables = $pdo->query(
            "SELECT name FROM {$schema}.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_values(array_filter($tables, function ($table) use ($pdo, $schema) {
            if (in_array($table, self::OPERATIONAL_TABLES, true)) {
                return false;
            }

            $columns = $pdo->query("PRAGMA {$schema}.table_info(".$pdo->quote($table).")")
                ->fetchAll(\PDO::FETCH_COLUMN, 1);

            return in_array('institution_id', $columns, true);
        }));
    }

    /** Colunas presentes simultaneamente em main e snapshot, na ordem de main. */
    protected function sharedColumns(\PDO $pdo, string $table): array
    {
        $main = $pdo->query('PRAGMA main.table_info('.$pdo->quote($table).')')->fetchAll(\PDO::FETCH_COLUMN, 1);
        $snapshot = $pdo->query('PRAGMA snapshot.table_info('.$pdo->quote($table).')')->fetchAll(\PDO::FETCH_COLUMN, 1);

        return array_values(array_intersect($main, $snapshot));
    }

    protected function restoreMysql(string $sqlFile): void
    {
        if (! $this->binaryExists('mysql')) {
            throw new \RuntimeException('Utilitário "mysql" não disponível para restauração.');
        }

        $process = $this->mysqlProcess([]);
        $process->setInput(fopen($sqlFile, 'r'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Falha ao restaurar banco MySQL: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * Restauração individual em MySQL: importa o dump em um banco temporário e
     * copia apenas as linhas da Câmara, preservando as demais.
     */
    protected function restoreMysqlInstitution(string $sqlFile, int $institutionId): void
    {
        if (! $this->binaryExists('mysql')) {
            throw new \RuntimeException('Utilitário "mysql" não disponível para restauração.');
        }

        $connection = config('database.connections.mysql', []);
        $mainDatabase = $connection['database'] ?? '';
        $temp = 'spi_restore_'.substr(md5(uniqid('', true)), 0, 10);

        $this->runMysqlStatement("CREATE DATABASE `{$temp}`");

        try {
            $import = $this->mysqlProcess(['--database='.$temp]);
            $import->setInput(fopen($sqlFile, 'r'));
            $import->run();

            if (! $import->isSuccessful()) {
                throw new \RuntimeException('Falha ao importar o dump temporário: '.trim($import->getErrorOutput()));
            }

            DB::beginTransaction();

            $tables = array_column(DB::select(
                'SELECT TABLE_NAME AS name FROM information_schema.columns
                 WHERE table_schema = ? AND column_name = ? GROUP BY TABLE_NAME',
                [$temp, 'institution_id']
            ), 'name');

            $tables = array_values(array_filter(
                $tables,
                fn ($table) => ! in_array($table, self::OPERATIONAL_TABLES, true)
            ));

            foreach ($tables as $table) {
                $mainColumns = array_column(DB::select(
                    'SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                    [$mainDatabase, $table]
                ), 'name');

                $tempColumns = array_column(DB::select(
                    'SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                    [$temp, $table]
                ), 'name');

                $columns = array_values(array_intersect($mainColumns, $tempColumns));
                if ($columns === []) {
                    continue;
                }

                $list = implode(', ', array_map(fn ($c) => '`'.$c.'`', $columns));

                DB::statement("DELETE FROM `{$mainDatabase}`.`{$table}` WHERE institution_id = ?", [$institutionId]);
                DB::statement(
                    "INSERT INTO `{$mainDatabase}`.`{$table}` ({$list})
                     SELECT {$list} FROM `{$temp}`.`{$table}` WHERE institution_id = ?",
                    [$institutionId]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $this->runMysqlStatement("DROP DATABASE IF EXISTS `{$temp}`");
        }
    }

    protected function runMysqlStatement(string $sql): void
    {
        $process = $this->mysqlProcess(['--execute='.$sql]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Falha ao executar comando MySQL: '.trim($process->getErrorOutput()));
        }
    }

    protected function mysqlProcess(array $extra): Process
    {
        $connection = config('database.connections.mysql', []);

        $command = array_merge([
            'mysql',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? ''),
        ], $extra);

        if (empty($extra) || ! collect($extra)->contains(fn ($v) => str_starts_with($v, '--database='))) {
            $command[] = $connection['database'] ?? '';
        }

        $process = new Process($command);
        $process->setTimeout(1200);

        if (! empty($connection['password'])) {
            $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        }

        return $process;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function readManifest(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Arquivo de backup inválido ou corrompido.');
        }

        $json = $zip->getFromName('manifest.json');
        $zip->close();

        if ($json === false) {
            throw new \RuntimeException('Manifesto ausente no arquivo de backup.');
        }

        $manifest = json_decode($json, true);

        if (! is_array($manifest)) {
            throw new \RuntimeException('Manifesto do backup inválido.');
        }

        return $manifest;
    }

    /** @return array<int,string> */
    protected function collectFiles(?int $institutionId): array
    {
        return Tenant::withoutScope(function () use ($institutionId) {
            $protocols = ProtocolAttachment::query()
                ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
                ->pluck('file_path');

            $propositions = PropositionAttachment::query()
                ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
                ->pluck('file_path');

            return $protocols->merge($propositions)->filter()->unique()->values()->all();
        });
    }

    /** @return array<string,int> */
    protected function counts(?int $institutionId): array
    {
        return Tenant::withoutScope(function () use ($institutionId) {
            $scope = fn ($query) => $institutionId
                ? $query->where('institution_id', $institutionId)
                : $query;

            return [
                'protocols' => $scope(Protocol::query())->count(),
                'propositions' => $scope(Proposition::query())->count(),
                'sectors' => $scope(Sector::query())->count(),
                'councilors' => $scope(Councilor::query())->count(),
                'users' => $scope(User::query())->count(),
            ];
        });
    }

    protected function fileSize(string $path): ?int
    {
        return Storage::disk('local')->exists($path)
            ? (int) Storage::disk('local')->size($path)
            : null;
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $target = rtrim($destination, '/\\').DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                @mkdir($target, 0775, true);
            } else {
                @mkdir(dirname($target), 0775, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
