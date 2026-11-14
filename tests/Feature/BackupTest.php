<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
use App\Jobs\GenerateBackupJob;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\Sector;
use App\Models\User;
use App\Services\BackupService;
use App\Services\ProtocolService;
use App\Support\BackupEncrypter;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * B2 — backup: fila, geração, hash, criptografia e restauração com salvaguardas.
 *
 * O serviço de backup manipula arquivos SQLite reais, por isso este teste usa
 * um banco em arquivo temporário em vez do :memory: padrão da suíte.
 */
class BackupTest extends TestCase
{
    protected string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->dbPath = storage_path('app/testing-'.uniqid('', true).'.sqlite');
        touch($this->dbPath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->dbPath,
        ]);

        DB::purge('sqlite');

        $this->artisan('migrate', ['--force' => true])->run();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        @unlink($this->dbPath);

        parent::tearDown();
    }

    public function test_backup_is_queued_without_blocking(): void
    {
        Queue::fake();

        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $backup = app(BackupService::class)->create(BackupType::INSTITUTION, $institution, $user->id);

        $this->assertSame(BackupStatus::NA_FILA, $backup->status);
        Queue::assertPushed(GenerateBackupJob::class, fn ($job) => $job->backupId === $backup->id);
    }

    public function test_generation_completes_with_hash_and_manifest(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $backup = $this->generate($institution, $user);

        $this->assertSame(BackupStatus::CONCLUIDO, $backup->status);
        $this->assertNotNull($backup->sha256);

        $absolute = Storage::disk('local')->path($backup->path);
        $this->assertSame(hash_file('sha256', $absolute), $backup->sha256);
        $this->assertFalse($backup->encrypted);
        $this->assertFalse($backup->manifest['encrypted']);
        $this->assertSame(BackupService::FORMAT_VERSION, $backup->manifest['version']);
    }

    public function test_generation_applies_encryption_when_enabled(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        BackupSetting::create([
            'institution_id' => $institution->id,
            'scope_key' => 'institution:'.$institution->id,
            'encrypt' => true,
        ]);

        $backup = $this->generate($institution, $user);
        $absolute = Storage::disk('local')->path($backup->path);

        $this->assertTrue($backup->encrypted);
        $this->assertTrue($backup->manifest['encrypted']);
        $this->assertTrue(BackupEncrypter::isEncryptedFile($absolute));
    }

    public function test_generation_is_not_duplicated_for_the_same_backup(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $service = app(BackupService::class);
        $backup = $service->generate($this->newBackupRecord($institution, $user), isSnapshot: true);

        $this->assertSame(BackupStatus::CONCLUIDO, $backup->status);
        $firstHash = $backup->sha256;
        $firstFinishedAt = $backup->finished_at;

        // Uma segunda execução do mesmo registro deve ser ignorada (claim atômico).
        $service->generate($backup);

        $backup->refresh();
        $this->assertSame($firstHash, $backup->sha256);
        $this->assertEquals($firstFinishedAt, $backup->finished_at);
        $this->assertSame(BackupStatus::CONCLUIDO, $backup->status);
    }

    public function test_individual_restore_creates_a_snapshot_and_preserves_other_chambers(): void
    {
        $a = $this->makeInstitution(['name' => 'Câmara A']);
        $b = $this->makeInstitution(['name' => 'Câmara B']);
        $userA = $this->makeUser($a);
        $userB = $this->makeUser($b);
        $sectorA = $this->makeSector($a);
        $sectorB = $this->makeSector($b);

        $protocolA = $this->createProtocol($a, $userA, $sectorA, 'PROTOCOLO-A');
        $protocolB = $this->createProtocol($b, $userB, $sectorB, 'PROTOCOLO-B');

        $backup = $this->generate($a, $userA);

        // Altera o estado após o backup.
        $protocolA->update(['subject' => 'PROTOCOLO-A-ALTERADO']);
        $protocolB->update(['subject' => 'PROTOCOLO-B-ALTERADO']);

        $result = app(BackupService::class)->restore($backup, $userA->id);

        // Salvaguarda automática gerada e concluída.
        $this->assertSame(BackupStatus::CONCLUIDO, $result['snapshot']->status);
        $this->assertTrue($result['snapshot']->manifest['snapshot']);

        // A Câmara restaurada volta ao estado do backup...
        $this->assertSame('PROTOCOLO-A', $protocolA->fresh()->subject);
        // ...e as demais Câmaras permanecem intactas.
        $this->assertSame('PROTOCOLO-B-ALTERADO', $protocolB->fresh()->subject);
    }

    public function test_restore_rejects_a_backup_from_another_chamber(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $userA = $this->makeUser($a);
        $userB = $this->makeUser($b);

        $backup = $this->generate($b, $userB);
        $absolute = Storage::disk('local')->path($backup->path);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outra Câmara');

        app(BackupService::class)->restoreFromUpload($absolute, $a->id, $userA->id);
    }

    public function test_restore_aborts_when_the_hash_diverges(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $backup = $this->generate($institution, $user);
        $backup->update(['sha256' => str_repeat('0', 64)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('integridade');

        app(BackupService::class)->restore($backup->fresh(), $user->id);
    }

    public function test_restore_blocks_backups_encrypted_with_another_key(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        BackupSetting::create([
            'institution_id' => $institution->id,
            'scope_key' => 'institution:'.$institution->id,
            'encrypt' => true,
        ]);

        $backup = $this->generate($institution, $user);

        // Simula a restauração em um servidor com APP_KEY diferente.
        config(['app.key' => 'base64:'.base64_encode(str_repeat('z', 32))]);

        $this->expectException(\RuntimeException::class);

        app(BackupService::class)->restore($backup->fresh(), $user->id);
    }

    public function test_failed_restore_recovers_the_previous_state(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);
        $sector = $this->makeSector($institution);

        $protocol = $this->createProtocol($institution, $user, $sector, 'PROTOCOLO-ORIGINAL');

        // Pacote com manifesto válido, mas banco de dados inválido: falha na ETAPA 3.
        $bogus = Storage::disk('local')->path('bogus-'.uniqid('', true).'.zip');
        $zip = new ZipArchive();
        $zip->open($bogus, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode([
            'version' => BackupService::FORMAT_VERSION,
            'institution' => ['id' => $institution->id, 'name' => $institution->name],
            'files' => [],
        ], JSON_UNESCAPED_UNICODE));
        $zip->addFromString('database.sqlite', 'isto-nao-e-um-banco-de-dados');
        $zip->close();

        $service = app(BackupService::class);

        try {
            $service->restoreFromUpload($bogus, $institution->id, $user->id);
            $this->fail('A restauração deveria ter falhado e acionado a recuperação.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('estado anterior foi recuperado', $e->getMessage());
        }

        // Estado original preservado após a recuperação automática.
        $this->assertSame('PROTOCOLO-ORIGINAL', $protocol->fresh()->subject);

        // Snapshot de recuperação registrado e concluído.
        $snapshots = Backup::where('institution_id', $institution->id)
            ->where('status', BackupStatus::CONCLUIDO)
            ->where('type', BackupType::INSTITUTION)
            ->get();

        $this->assertCount(1, $snapshots);
        $this->assertTrue($snapshots->first()->manifest['snapshot']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function generate(Institution $institution, User $user): Backup
    {
        $service = app(BackupService::class);
        $backup = $service->create(BackupType::INSTITUTION, $institution, $user->id);

        return $backup->refresh();
    }

    private function newBackupRecord(Institution $institution, User $user): Backup
    {
        return Backup::create([
            'institution_id' => $institution->id,
            'type' => BackupType::INSTITUTION,
            'filename' => 'snapshot-test-'.uniqid().'.zip',
            'disk' => 'local',
            'path' => 'backups/snapshot-test-'.uniqid().'.zip',
            'status' => BackupStatus::NA_FILA,
            'created_by' => $user->id,
        ]);
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject): Protocol
    {
        return Tenant::withoutScope(fn () => app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => $subject,
            'sender_name' => 'Interessado',
            'destination_sector_id' => $sector->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $user));
    }
}
