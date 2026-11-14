<?php

namespace Tests\Feature\Homologacao;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
use App\Enums\PropositionModality;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\ProtocolMovement;
use App\Models\Proposition;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use App\Services\PropositionService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homologação operacional — Cenário 1: isolamento multi-Câmaras.
 *
 * Duas instituições reais, com usuários, setores, protocolos, movimentações,
 * anexos, proposições, auditoria e backups. Verifica isolamento por HTTP
 * (acesso direto por URL e manipulação manual de IDs).
 */
class MultiCamarasHomologTest extends TestCase
{
    use RefreshDatabase;

    private Institution $alfa;

    private Institution $beta;

    private User $adminAlfa;

    private User $adminBeta;

    private Sector $setorAlfa;

    private Sector $setorBeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->alfa = $this->makeInstitution(['name' => 'Câmara Alfa']);
        $this->beta = $this->makeInstitution(['name' => 'Câmara Beta']);

        $this->adminAlfa = $this->makeUser($this->alfa, 'administrador', [
            'name' => 'ADMIN-ALFA', 'email' => 'alfa@homolog.test',
        ]);
        $this->adminBeta = $this->makeUser($this->beta, 'administrador', [
            'name' => 'ADMIN-BETA', 'email' => 'beta@homolog.test',
        ]);

        $this->setorAlfa = $this->makeSector($this->alfa, 'Protocolo Alfa');
        $this->setorBeta = $this->makeSector($this->beta, 'Protocolo Beta');
    }

    public function test_isolamento_de_usuarios(): void
    {
        $this->actingAs($this->adminAlfa)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('ADMIN-ALFA')
            ->assertDontSee('ADMIN-BETA');
    }

    public function test_isolamento_de_protocolos_movimentacoes_e_anexos(): void
    {
        $protocoloBeta = $this->protocoloNaBeta();

        ProtocolAttachment::withoutGlobalScopes()->create([
            'institution_id' => $this->beta->id,
            'protocol_id' => $protocoloBeta->id,
            'original_name' => 'ANEXO-BETA-SECRETO.pdf',
            'file_path' => 'attachments/'.$this->beta->id.'/'.$protocoloBeta->id.'/a.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'hash_sha256' => str_repeat('b', 64),
            'uploaded_by' => $this->adminBeta->id,
        ]);

        $anexoBeta = ProtocolAttachment::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->actingAs($this->adminAlfa);

        // Listagem não expõe protocolos de outra Câmara.
        $this->get(route('protocols.index'))
            ->assertOk()
            ->assertDontSee('PROTOCOLO-BETA-SECRETO');

        // Acesso direto por URL e por anexo: bloqueado.
        $this->get(route('protocols.show', $protocoloBeta))->assertNotFound();
        $this->get(route('protocols.edit', $protocoloBeta))->assertNotFound();
        $this->get(route('protocols.attachments.download', $anexoBeta))->assertNotFound();

        // Caixa de entrada não apresenta movimentações de outra Câmara.
        $this->get(route('protocols.inbox'))
            ->assertOk()
            ->assertDontSee('PROTOCOLO-BETA-SECRETO');

        // Nenhuma movimentação da Câmara Beta é visível sob o escopo da Alfa.
        Tenant::set($this->alfa);
        $this->assertSame(0, ProtocolMovement::where('protocol_id', $protocoloBeta->id)->count());
        Tenant::set(null);
    }

    public function test_isolamento_de_proposicoes(): void
    {
        $proposicaoBeta = Tenant::withoutScope(fn () => app(PropositionService::class)->create([
            'institution_id' => $this->beta->id,
            'modality' => PropositionModality::PROJETO_LEI->value,
            'author_councilor_id' => $this->makeCouncilor($this->beta)->id,
            'title' => 'PROPOSICAO-BETA-SECRETA',
            'ementa' => 'Matéria sigilosa da Beta.',
        ], $this->adminBeta));

        $this->actingAs($this->adminAlfa);

        $this->get(route('propositions.index'))
            ->assertOk()
            ->assertDontSee('PROPOSICAO-BETA-SECRETA');

        $this->get(route('propositions.show', $proposicaoBeta))->assertNotFound();
    }

    public function test_isolamento_de_auditoria(): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $this->alfa->id,
            'user_id' => $this->adminAlfa->id,
            'action' => 'teste',
            'description' => 'LOG-ALFA-PROPRIO',
        ]);

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $this->beta->id,
            'user_id' => $this->adminBeta->id,
            'action' => 'teste',
            'description' => 'LOG-BETA-SECRETO',
        ]);

        $this->actingAs($this->adminAlfa)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('LOG-ALFA-PROPRIO')
            ->assertDontSee('LOG-BETA-SECRETO');
    }

    public function test_isolamento_de_backups(): void
    {
        $backupAlfa = $this->registroBackup($this->alfa, 'BACKUP-ALFA.zip');
        $backupBeta = $this->registroBackup($this->beta, 'BACKUP-BETA.zip');

        $this->actingAs($this->adminAlfa)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('BACKUP-ALFA.zip')
            ->assertDontSee('BACKUP-BETA.zip');

        // Download de backup de outra Câmara: proibido.
        $this->actingAs($this->adminAlfa)
            ->get(route('backups.download', $backupBeta))
            ->assertForbidden();
    }

    public function test_acesso_direto_por_url_e_manipulacao_de_ids_sao_bloqueados(): void
    {
        $protocoloBeta = $this->protocoloNaBeta();
        $movimentoBeta = ProtocolMovement::withoutGlobalScopes()
            ->where('protocol_id', $protocoloBeta->id)->latest('id')->firstOrFail();

        $this->actingAs($this->adminAlfa);

        // URL direta com ID de outra Câmara.
        $this->get(route('protocols.show', $protocoloBeta))->assertNotFound();

        // Manipulação de ID em ação de tramitação.
        $this->post(route('protocols.receive', $movimentoBeta), [
            'description' => 'tentativa indevida',
        ])->assertNotFound();

        // Confirma que o movimento de outra Câmara permaneceu intacto.
        $this->assertSame(
            \App\Enums\MovementStatus::AGUARDANDO_RECEBIMENTO,
            $movimentoBeta->fresh()->status
        );
    }

    public function test_cadastro_com_setor_de_outra_camara_e_rejeitado(): void
    {
        // Manipulação manual de ID: setor de destino pertencente à Câmara Beta.
        $this->actingAs($this->adminAlfa)
            ->from(route('protocols.create'))
            ->post(route('protocols.store'), [
                'document_type' => DocumentType::OFICIO->value,
                'subject' => 'Tentativa com setor externo',
                'sender_name' => 'Interessado',
                'destination_sector_id' => $this->setorBeta->id,
                'priority' => ProtocolPriority::NORMAL->value,
            ])
            ->assertSessionHasErrors('destination_sector_id');

        $this->assertSame(0, Protocol::withoutGlobalScopes()->count());
    }

    public function test_super_admin_tem_acesso_global(): void
    {
        $super = $this->makeSuperAdmin();
        $protocoloBeta = $this->protocoloNaBeta();

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $this->alfa->id, 'action' => 'teste', 'description' => 'LOG-ALFA-XYZ',
        ]);
        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $this->beta->id, 'action' => 'teste', 'description' => 'LOG-BETA-XYZ',
        ]);

        $this->actingAs($super);

        $this->get(route('protocols.show', $protocoloBeta))->assertOk();

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertSee('LOG-ALFA-XYZ')
            ->assertSee('LOG-BETA-XYZ');
    }

    private function protocoloNaBeta(): Protocol
    {
        return Tenant::withoutScope(fn () => app(ProtocolService::class)->create([
            'institution_id' => $this->beta->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => 'PROTOCOLO-BETA-SECRETO',
            'sender_name' => 'Interessado Beta',
            'destination_sector_id' => $this->setorBeta->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $this->adminBeta));
    }

    private function registroBackup(Institution $institution, string $filename): Backup
    {
        return Backup::create([
            'institution_id' => $institution->id,
            'type' => BackupType::INSTITUTION,
            'filename' => $filename,
            'disk' => 'local',
            'path' => 'backups/'.$filename,
            'status' => BackupStatus::CONCLUIDO,
            'created_by' => $institution->id === $this->alfa->id
                ? $this->adminAlfa->id
                : $this->adminBeta->id,
        ]);
    }
}
