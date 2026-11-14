<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B2 — isolamento multi-Câmaras (protocolos, anexos e auditoria).
 */
class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_chamber_cannot_see_or_open_protocols_of_another_chamber(): void
    {
        $a = $this->makeInstitution(['name' => 'Câmara A']);
        $b = $this->makeInstitution(['name' => 'Câmara B']);
        $adminA = $this->makeUser($a);
        $userB = $this->makeUser($b);
        $sectorB = $this->makeSector($b);

        $protocolB = $this->createProtocol($b, $userB, $sectorB, 'PROTOCOLO-SECRETO-B');

        // Acesso direto ao recurso de outra Câmara: negado pelo escopo global.
        $this->actingAs($adminA)
            ->get(route('protocols.show', $protocolB))
            ->assertNotFound();

        // A listagem não expõe protocolos de outra Câmara.
        $this->actingAs($adminA)
            ->get(route('protocols.index'))
            ->assertOk()
            ->assertDontSee('PROTOCOLO-SECRETO-B');
    }

    public function test_a_chamber_cannot_download_attachments_of_another_chamber(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $userB = $this->makeUser($b);
        $sectorB = $this->makeSector($b);

        $protocolB = $this->createProtocol($b, $userB, $sectorB, 'PROTOCOLO-B');
        $attachmentB = ProtocolAttachment::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'protocol_id' => $protocolB->id,
            'original_name' => 'documento-b.pdf',
            'file_path' => 'attachments/'.$b->id.'/'.$protocolB->id.'/documento-b.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'hash_sha256' => str_repeat('a', 64),
            'uploaded_by' => $userB->id,
        ]);

        $this->actingAs($adminA)
            ->get(route('protocols.attachments.download', $attachmentB))
            ->assertNotFound();
    }

    public function test_a_chamber_cannot_see_audit_logs_of_another_chamber(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $adminB = $this->makeUser($b);

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $a->id,
            'user_id' => $adminA->id,
            'action' => 'protocol.created',
            'description' => 'LOG-PROPRIO-CAMARA-A',
        ]);

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'user_id' => $adminB->id,
            'action' => 'protocol.created',
            'description' => 'LOG-SECRETO-CAMARA-B',
        ]);

        $this->actingAs($adminA)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('LOG-PROPRIO-CAMARA-A')
            ->assertDontSee('LOG-SECRETO-CAMARA-B');
    }

    public function test_super_admin_has_global_access_to_audit_logs(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $super = $this->makeSuperAdmin();

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $a->id,
            'action' => 'protocol.created',
            'description' => 'LOG-CAMARA-A',
        ]);

        AuditLog::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'action' => 'protocol.created',
            'description' => 'LOG-CAMARA-B',
        ]);

        $this->actingAs($super)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('LOG-CAMARA-A')
            ->assertSee('LOG-CAMARA-B');
    }

    public function test_super_admin_can_open_protocols_of_any_chamber(): void
    {
        $b = $this->makeInstitution();
        $userB = $this->makeUser($b);
        $sectorB = $this->makeSector($b);
        $super = $this->makeSuperAdmin();

        $protocolB = $this->createProtocol($b, $userB, $sectorB, 'PROTOCOLO-B');

        $this->actingAs($super)
            ->get(route('protocols.show', $protocolB))
            ->assertOk();
    }

    private function createProtocol(Institution $institution, User $user, Sector $sector, string $subject): Protocol
    {
        return app(ProtocolService::class)->create([
            'institution_id' => $institution->id,
            'document_type' => DocumentType::OFICIO->value,
            'subject' => $subject,
            'sender_name' => 'Interessado',
            'destination_sector_id' => $sector->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ], $user);
    }
}
