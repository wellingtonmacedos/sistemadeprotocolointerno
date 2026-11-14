<?php

namespace Tests\Feature\Homologacao;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Homologação operacional — Cenário 4: backup e restauração.
 *
 * Executa pela via operacional (HTTP) o ciclo completo: backup individual,
 * backup de plataforma, download, verificação SHA-256, criptografia,
 * restauração individual, verificação de integridade, falha controlada e
 * recuperação do snapshot — confirmando que outra Câmara permanece intacta.
 */
class BackupHomologTest extends TestCase
{
    protected string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // O serviço manipula arquivos SQLite reais: usamos banco em arquivo.
        $this->dbPath = storage_path('app/homolog-'.uniqid('', true).'.sqlite');
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

    public function test_backup_individual_com_download_hash_e_criptografia(): void
    {
        $camara = $this->makeInstitution(['name' => 'Câmara Backup']);
        $user = $this->makeUser($camara, 'administrador');

        BackupSetting::create([
            'institution_id' => $camara->id,
            'scope_key' => 'institution:'.$camara->id,
            'encrypt' => true,
        ]);

        // 1) Backup individual solicitado pelo administrador da Câmara.
        $this->actingAs($user)
            ->post(route('backups.store'), ['type' => 'institution'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $backup = Backup::where('institution_id', $camara->id)->latest('id')->firstOrFail();

        $this->assertSame(BackupType::INSTITUTION, $backup->type);
        $this->assertSame(BackupStatus::CONCLUIDO, $backup->status);

        // 2) Download do arquivo.
        $this->actingAs($user)
            ->get(route('backups.download', $backup))
            ->assertOk();

        // 3) Verificação SHA-256 do pacote.
        $absolute = Storage::disk('local')->path($backup->path);
        $this->assertSame(hash_file('sha256', $absolute), $backup->sha256);

        // 4) Verificação de criptografia (AES-256-GCM).
        $this->assertTrue($backup->encrypted);
        $this->assertTrue($backup->manifest['encrypted']);
        $this->assertTrue(BackupEncrypter::isEncryptedFile($absolute));
    }

    public function test_backup_de_plataforma_e_exclusivo_do_super_admin(): void
    {
        $super = $this->makeSuperAdmin();

        $this->actingAs($super)
            ->post(route('backups.store'), ['type' => 'platform'])
            ->assertRedirect();

        $plataforma = Backup::where('type', BackupType::PLATFORM)
            ->whereNull('institution_id')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(BackupStatus::CONCLUIDO, $plataforma->status);

        // Administrador de Câmara não consegue gerar backup de plataforma:
        // a solicitação recai como backup da própria Câmara.
        $camara = $this->makeInstitution();
        $admin = $this->makeUser($camara, 'administrador');

        $this->actingAs($admin)
            ->post(route('backups.store'), ['type' => 'platform'])
            ->assertRedirect();

        $daCamara = Backup::where('institution_id', $camara->id)->latest('id')->firstOrFail();
        $this->assertSame(BackupType::INSTITUTION, $daCamara->type);
    }

    public function test_restauracao_individual_preserva_a_outra_camara(): void
    {
        $a = $this->makeInstitution(['name' => 'Câmara A']);
        $b = $this->makeInstitution(['name' => 'Câmara B']);
        $userA = $this->makeUser($a, 'administrador');
        $userB = $this->makeUser($b, 'administrador');
        $sectorA = $this->makeSector($a);
        $sectorB = $this->makeSector($b);

        $protocolA = $this->createProtocol($a, $userA, $sectorA, 'PROTOCOLO-A');
        $protocolB = $this->createProtocol($b, $userB, $sectorB, 'PROTOCOLO-B');

        // Backup individual da Câmara A (execução síncrona em testes).
        $backup = app(BackupService::class)->create(BackupType::INSTITUTION, $a, $userA->id)->refresh();
        $this->assertSame(BackupStatus::CONCLUIDO, $backup->status);

        // Estado é alterado após o backup.
        $protocolA->update(['subject' => 'PROTOCOLO-A-ALTERADO']);
        $protocolB->update(['subject' => 'PROTOCOLO-B-ALTERADO']);

        // Restauração individual via HTTP.
        $this->actingAs($userA)
            ->post(route('backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Integridade: a Câmara A volta ao estado do backup.
        $this->assertSame('PROTOCOLO-A', $protocolA->fresh()->subject);
        // Isolamento: a Câmara B permanece intacta.
        $this->assertSame('PROTOCOLO-B-ALTERADO', $protocolB->fresh()->subject);

        // Salvaguarda automática (snapshot) registrada e concluída.
        $snapshots = Backup::where('institution_id', $a->id)
            ->where('type', BackupType::INSTITUTION)
            ->where('status', BackupStatus::CONCLUIDO)
            ->get()
            ->filter(fn (Backup $b) => ($b->manifest['snapshot'] ?? false) === true);

        $this->assertCount(1, $snapshots);
    }

    public function test_falha_controlada_recupera_o_snapshot(): void
    {
        $camara = $this->makeInstitution(['name' => 'Câmara Falha']);
        $user = $this->makeUser($camara, 'administrador');
        $sector = $this->makeSector($camara);

        $protocol = $this->createProtocol($camara, $user, $sector, 'PROTOCOLO-ORIGINAL');

        // Pacote com manifesto válido, mas banco de dados inválido: falha na aplicação.
        $bogus = storage_path('app/bogus-'.uniqid('', true).'.zip');
        $zip = new ZipArchive();
        $zip->open($bogus, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode([
            'version' => BackupService::FORMAT_VERSION,
            'institution' => ['id' => $camara->id, 'name' => $camara->name],
            'files' => [],
        ], JSON_UNESCAPED_UNICODE));
        $zip->addFromString('database.sqlite', 'isto-nao-e-um-banco-de-dados');
        $zip->close();

        $upload = new UploadedFile($bogus, 'restauracao.zip', 'application/zip', null, true);

        $this->actingAs($user)
            ->post(route('backups.restore.upload'), ['file' => $upload])
            ->assertRedirect()
            ->assertSessionHas('error');

        @unlink($bogus);

        // Estado anterior recuperado automaticamente.
        $this->assertSame('PROTOCOLO-ORIGINAL', $protocol->fresh()->subject);

        $snapshot = Backup::where('institution_id', $camara->id)
            ->where('status', BackupStatus::CONCLUIDO)
            ->get()
            ->first(fn (Backup $b) => ($b->manifest['snapshot'] ?? false) === true);

        $this->assertNotNull($snapshot, 'A salvaguarda de recuperação deveria existir.');
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
