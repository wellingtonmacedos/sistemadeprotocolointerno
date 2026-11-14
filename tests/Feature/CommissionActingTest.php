<?php

namespace Tests\Feature;

use App\Enums\ForwardingStatus;
use App\Enums\PropositionStatus;
use App\Models\Commission;
use App\Models\CommissionMember;
use App\Models\CommissionOpinion;
use App\Models\Proposition;
use App\Models\PropositionForwarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Atuação por comissão: quem é membro ativo pode receber/movimentar os
 * encaminhamentos da sua comissão; relator e presidente podem registrar
 * pareceres — ainda que o perfil do usuário não tenha a permissão global.
 */
class CommissionActingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function makeProposition($institution, array $attributes = []): Proposition
    {
        return Proposition::withoutGlobalScopes()->create(array_merge([
            'institution_id' => $institution->id,
            'modality' => 'projeto_lei',
            'year' => (int) now()->year,
            'title' => 'Projeto de Lei de teste',
            'ementa' => 'Ementa de teste',
            'status' => PropositionStatus::APROVADA->value,
        ], $attributes));
    }

    private function makeCommission($institution, string $acronym = 'CCJ'): Commission
    {
        return Commission::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'name' => 'Comissão '.$acronym,
            'acronym' => $acronym,
            'type' => 'permanente',
            'is_active' => true,
        ]);
    }

    /** Cria um usuário "vereador" vinculado a um mandato ativo na comissão. */
    private function makeMember($institution, Commission $commission, string $role): User
    {
        $councilor = $this->makeCouncilor($institution);

        CommissionMember::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'commission_id' => $commission->id,
            'councilor_id' => $councilor->id,
            'role' => $role,
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        return $this->makeUser($institution, 'vereador', ['councilor_id' => $councilor->id]);
    }

    private function makeForwarding($institution, Proposition $proposition, Commission $commission, User $forwarder): PropositionForwarding
    {
        return PropositionForwarding::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'proposition_id' => $proposition->id,
            'commission_id' => $commission->id,
            'status' => ForwardingStatus::AGUARDANDO_RECEBIMENTO,
            'forwarded_by' => $forwarder->id,
            'forwarded_at' => now(),
        ]);
    }

    public function test_active_commission_member_can_receive_forwarding(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $member = $this->makeMember($institution, $commission, 'membro');

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        $this->actingAs($member)
            ->post(route('propositions.forwardings.receive', $forwarding))
            ->assertRedirect();

        $forwarding->refresh();
        $this->assertSame(ForwardingStatus::EM_ANALISE, $forwarding->status);
        $this->assertNotNull($forwarding->received_at);
        $this->assertSame($member->id, $forwarding->received_by);
    }

    public function test_outsider_councilor_cannot_receive_forwarding(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);

        // Vereador não vinculado a nenhum mandato desta comissão.
        $outsider = $this->makeUser($institution, 'vereador', [
            'councilor_id' => $this->makeCouncilor($institution)->id,
        ]);

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        $this->actingAs($outsider)
            ->post(route('propositions.forwardings.receive', $forwarding))
            ->assertForbidden();

        $this->assertNull($forwarding->refresh()->received_at);
    }

    public function test_active_commission_member_can_update_forwarding_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $member = $this->makeMember($institution, $commission, 'membro');

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        $this->actingAs($member)
            ->post(route('propositions.forwardings.status', $forwarding), ['status' => 'aguardando_parecer'])
            ->assertRedirect();

        $this->assertSame(ForwardingStatus::AGUARDANDO_PARECER, $forwarding->refresh()->status);
    }

    public function test_commission_rapporteur_can_register_opinion(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $rapporteur = $this->makeMember($institution, $commission, 'relator');

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        $this->actingAs($rapporteur)
            ->post(route('propositions.opinions.store', $proposition), [
                'commission_id' => $commission->id,
                'forwarding_id' => $forwarding->id,
                'result' => 'favoravel',
                'text' => 'Parecer favorável pela aprovação.',
            ])->assertRedirect();

        $this->assertSame(1, CommissionOpinion::withoutGlobalScopes()->count());
        $this->assertSame(ForwardingStatus::PARECER_FAVORAVEL, $forwarding->refresh()->status);
    }

    public function test_commission_president_can_register_opinion(): void
    {
        $institution = $this->makeInstitution();
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $president = $this->makeMember($institution, $commission, 'presidente');

        $this->actingAs($president)
            ->post(route('propositions.opinions.store', $proposition), [
                'commission_id' => $commission->id,
                'result' => 'contrario',
                'text' => 'Parecer contrário à aprovação.',
            ])->assertRedirect();

        $this->assertSame(1, CommissionOpinion::withoutGlobalScopes()->count());
    }

    public function test_plain_member_cannot_register_opinion(): void
    {
        $institution = $this->makeInstitution();
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $member = $this->makeMember($institution, $commission, 'membro');

        $this->actingAs($member)
            ->post(route('propositions.opinions.store', $proposition), [
                'commission_id' => $commission->id,
                'result' => 'favoravel',
                'text' => 'Parecer do membro.',
            ])->assertForbidden();

        $this->assertSame(0, CommissionOpinion::withoutGlobalScopes()->count());
    }

    public function test_commission_secretary_can_receive_but_not_register_opinion(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution);
        $secretary = $this->makeMember($institution, $commission, 'secretario');

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        // Sendo membro ativo, o secretário recebe o encaminhamento normalmente.
        $this->actingAs($secretary)
            ->post(route('propositions.forwardings.receive', $forwarding))
            ->assertRedirect();

        $this->assertNotNull($forwarding->refresh()->received_at);

        // Mas não registra parecer (restrito a relator/presidente).
        $this->actingAs($secretary)
            ->post(route('propositions.opinions.store', $proposition), [
                'commission_id' => $commission->id,
                'forwarding_id' => $forwarding->id,
                'result' => 'favoravel',
                'text' => 'Parecer do secretário.',
            ])->assertForbidden();

        $this->assertSame(0, CommissionOpinion::withoutGlobalScopes()->count());
    }

    public function test_opinion_does_not_change_proposition_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $commission = $this->makeCommission($institution);
        $proposition = $this->makeProposition($institution, ['status' => PropositionStatus::EM_ANALISE->value]);
        $rapporteur = $this->makeMember($institution, $commission, 'relator');

        $forwarding = $this->makeForwarding($institution, $proposition, $commission, $admin);

        $this->actingAs($rapporteur)
            ->post(route('propositions.opinions.store', $proposition), [
                'commission_id' => $commission->id,
                'forwarding_id' => $forwarding->id,
                'result' => 'favoravel',
                'text' => 'Parecer favorável.',
            ])->assertRedirect();

        // Parecer é consultivo: a situação da proposição permanece inalterada.
        $this->assertSame(PropositionStatus::EM_ANALISE, $proposition->refresh()->status);
    }
}
