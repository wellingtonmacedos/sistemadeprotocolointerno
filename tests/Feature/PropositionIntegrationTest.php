<?php

namespace Tests\Feature;

use App\Enums\ForwardingStatus;
use App\Enums\OpinionResult;
use App\Models\Commission;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Models\CommissionOpinion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Integração com proposições: encaminhamentos, pareceres e histórico.
 */
class PropositionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    protected function makeProposition($institution, array $attributes = []): Proposition
    {
        return Proposition::withoutGlobalScopes()->create(array_merge([
            'institution_id' => $institution->id,
            'modality' => 'projeto_lei',
            'year' => (int) now()->year,
            'title' => 'Projeto de Lei de teste',
            'ementa' => 'Ementa de teste',
            'status' => 'aprovada',
        ], $attributes));
    }

    protected function makeCommission($institution, string $acronym = 'CCJ'): Commission
    {
        return Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => 'Comissão '.$acronym,
            'acronym' => $acronym,
            'type' => 'permanente',
            'is_active' => true,
        ]);
    }

    public function test_forwards_proposition_to_commission_and_confirms_receipt(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commission->id,
            'deadline' => '2026-01-31',
            'notes' => 'Encaminhamento de teste',
        ])->assertRedirect();

        $forwarding = PropositionForwarding::firstOrFail();
        $this->assertSame(ForwardingStatus::AGUARDANDO_RECEBIMENTO, $forwarding->status);
        $this->assertSame($institution->id, $forwarding->institution_id);
        $this->assertNotNull($forwarding->forwarded_at);

        $this->actingAs($admin)
            ->post(route('propositions.forwardings.receive', $forwarding))
            ->assertRedirect();

        $forwarding->refresh();
        $this->assertSame(ForwardingStatus::EM_ANALISE, $forwarding->status);
        $this->assertNotNull($forwarding->received_at);
        $this->assertSame($admin->id, $forwarding->received_by);
    }

    public function test_registers_opinion_and_updates_the_forwarding_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $councilor = $this->makeCouncilor($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commission->id,
        ])->assertRedirect();

        $forwarding = PropositionForwarding::firstOrFail();

        $this->actingAs($admin)->post(route('propositions.opinions.store', $proposition), [
            'commission_id' => $commission->id,
            'forwarding_id' => $forwarding->id,
            'rapporteur_councilor_id' => $councilor->id,
            'result' => 'favoravel',
            'text' => 'Parecer favorável pela aprovação.',
            'opinion_date' => '2026-01-20',
        ])->assertRedirect();

        $opinion = CommissionOpinion::firstOrFail();
        $this->assertSame(OpinionResult::FAVORAVEL, $opinion->result);
        $this->assertSame($commission->id, $opinion->commission_id);
        $this->assertSame($councilor->id, $opinion->rapporteur_councilor_id);

        // O parecer não altera a situação geral da proposição (não é aprovação em Plenário).
        $this->assertSame('aprovada', $proposition->fresh()->status->value);

        // Atualiza a situação do encaminhamento correspondente.
        $this->assertSame(ForwardingStatus::PARECER_FAVORAVEL, $forwarding->fresh()->status);
    }

    public function test_cannot_forward_to_a_commission_of_another_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $commissionB = $this->makeCommission($b, 'CB');
        $proposition = $this->makeProposition($a);

        $this->actingAs($adminA)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commissionB->id,
        ])->assertSessionHasErrors('commission_id');

        $this->assertSame(0, PropositionForwarding::withoutGlobalScopes()->count());
    }

    public function test_cannot_manage_forwardings_of_another_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $adminB = $this->makeUser($b);
        $commissionB = $this->makeCommission($b, 'CB');
        $propositionB = $this->makeProposition($b);

        $forwardingB = PropositionForwarding::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'proposition_id' => $propositionB->id,
            'commission_id' => $commissionB->id,
            'status' => ForwardingStatus::AGUARDANDO_RECEBIMENTO,
            'forwarded_by' => $adminB->id,
            'forwarded_at' => now(),
        ]);

        $this->actingAs($adminA)
            ->post(route('propositions.forwardings.receive', $forwardingB))
            ->assertNotFound();

        $this->assertNull($forwardingB->fresh()->received_at);
    }

    public function test_timeline_records_forwarding_and_opinion_events(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution, 'CFO');
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commission->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('propositions.opinions.store', $proposition), [
            'commission_id' => $commission->id,
            'result' => 'contrario',
            'text' => 'Parecer contrário.',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('propositions.show', $proposition))
            ->assertOk()
            ->assertSee('Encaminhada à comissão')
            ->assertSee('Parecer registrado');
    }

    public function test_opinion_removal_requires_a_justification(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('propositions.opinions.store', $proposition), [
            'commission_id' => $commission->id,
            'result' => 'favoravel',
            'text' => 'Parecer.',
        ])->assertRedirect();

        $opinion = CommissionOpinion::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('propositions.opinions.destroy', $opinion), ['justification' => ''])
            ->assertSessionHasErrors('justification');

        $this->assertNull($opinion->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('propositions.opinions.destroy', $opinion), ['justification' => 'Erro de digitação no parecer'])
            ->assertRedirect();

        $this->assertSoftDeleted('commission_opinions', ['id' => $opinion->id]);
    }

    public function test_forwarding_update_status_marks_completion(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commission->id,
        ])->assertRedirect();

        $forwarding = PropositionForwarding::firstOrFail();

        $this->actingAs($admin)->post(route('propositions.forwardings.status', $forwarding), [
            'status' => 'concluida',
        ])->assertRedirect();

        $forwarding->refresh();
        $this->assertSame(ForwardingStatus::CONCLUIDA, $forwarding->status);
        $this->assertNotNull($forwarding->completed_at);
    }

    public function test_user_without_permission_cannot_forward(): void
    {
        $institution = $this->makeInstitution();
        $councilorUser = $this->makeUser($institution, 'vereador');
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($councilorUser)->post(route('propositions.forwardings.store', $proposition), [
            'commission_id' => $commission->id,
        ])->assertForbidden();

        $this->assertSame(0, PropositionForwarding::withoutGlobalScopes()->count());
    }
}
