<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\MovementStatus;
use App\Enums\ProtocolPriority;
use App\Models\Protocol;
use App\Models\ProtocolMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B2 — fluxo completo: login -> cadastro -> encaminhamento -> recebimento ->
 * anexo -> timeline -> PDF.
 */
class ProtocolFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_full_protocol_flow_from_login_to_pdf(): void
    {
        Storage::fake('local');

        $institution = $this->makeInstitution();
        $sector = $this->makeSector($institution, 'Protocolo');
        $this->makeUser($institution, 'administrador', ['email' => 'fluxo@example.test']);

        // 1. Login
        $this->post(route('login.attempt'), [
            'email' => 'fluxo@example.test',
            'password' => 'Senha@12345',
        ])->assertRedirect(route('painel'));

        $this->assertAuthenticated();

        // 2. Cadastro do protocolo (numeração automática + encaminhamento inicial)
        $this->post(route('protocols.store'), [
            'document_type' => DocumentType::OFICIO->value,
            'subject' => 'Solicitação de fluxo completo',
            'sender_name' => 'Cidadão Teste',
            'destination_sector_id' => $sector->id,
            'priority' => ProtocolPriority::NORMAL->value,
        ])->assertRedirect();

        $protocol = Protocol::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertNotNull($protocol->formatted_number);
        $this->assertSame(1, $protocol->number);

        // 3. Encaminhamento
        $this->post(route('protocols.forward', $protocol), [
            'to_sector_id' => $sector->id,
            'description' => 'Encaminhado para análise.',
        ])->assertRedirect();

        $movement = ProtocolMovement::withoutGlobalScopes()->latest('id')->firstOrFail();

        // 4. Recebimento pelo setor
        $this->post(route('protocols.receive', $movement))->assertRedirect();

        $this->assertSame(MovementStatus::RECEBIDO, $movement->fresh()->status);

        // 5. Anexo
        $this->post(route('protocols.attachments.store', $protocol), [
            'file' => UploadedFile::fake()->create('documento.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, $protocol->fresh()->attachments()->count());

        // 6. Timeline (detalhamento)
        $this->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee('Solicitação de fluxo completo');

        // 7. PDF do comprovante
        $pdf = $this->get(route('protocols.receipt', $protocol));

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }
}
