<?php

namespace Tests\Feature;

use App\Enums\PropositionModality;
use App\Enums\PropositionOrigin;
use App\Enums\PropositionStatus;
use App\Models\Institution;
use App\Models\Proposition;
use App\Models\User;
use App\Services\PropositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B2 — fluxo das proposições: rascunho -> envio -> aprovação -> registro oficial.
 */
class PropositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_proposition_flow_reaches_official_registration(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $councilor = $this->makeCouncilor($institution);

        $this->actingAs($admin);

        $this->post(route('propositions.store'), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'author_councilor_id' => $councilor->id,
            'title' => 'Projeto de Lei de teste',
            'ementa' => 'Dispõe sobre matéria de teste.',
        ])->assertRedirect();

        $proposition = Proposition::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(PropositionStatus::RASCUNHO, $proposition->status);

        $this->post(route('propositions.submit', $proposition))->assertRedirect();
        $this->assertSame(PropositionStatus::ENVIADA, $proposition->fresh()->status);

        $this->post(route('propositions.review', $proposition), ['decision' => 'aprovada'])->assertRedirect();
        $this->assertSame(PropositionStatus::APROVADA, $proposition->fresh()->status);

        $this->post(route('propositions.register', $proposition))->assertRedirect();

        $registered = $proposition->fresh();
        $this->assertSame(PropositionStatus::REGISTRADA, $registered->status);
        $this->assertStringStartsWith('PL nº', (string) $registered->official_number);
    }

    public function test_devolucao_allows_editing_again(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $councilor = $this->makeCouncilor($institution);

        $this->actingAs($admin);

        $this->post(route('propositions.store'), [
            'modality' => PropositionModality::INDICACAO->value,
            'author_councilor_id' => $councilor->id,
            'title' => 'Indicação de teste',
            'ementa' => 'Sugere melhoria.',
        ])->assertRedirect();

        $proposition = Proposition::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->post(route('propositions.submit', $proposition))->assertRedirect();
        $this->post(route('propositions.review', $proposition), [
            'decision' => 'devolvida',
            'notes' => 'Falta justificativa.',
        ])->assertRedirect();

        $this->assertSame(PropositionStatus::DEVOLVIDA, $proposition->fresh()->status);

        $this->get(route('propositions.edit', $proposition))->assertOk();
    }

    public function test_numbering_is_sequential_per_modality(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $councilor = $this->makeCouncilor($institution);
        $service = app(PropositionService::class);

        $first = $this->register($service, $institution, $admin, $councilor->id, PropositionModality::PROJETO_LEI);
        $second = $this->register($service, $institution, $admin, $councilor->id, PropositionModality::PROJETO_LEI);
        $other = $this->register($service, $institution, $admin, $councilor->id, PropositionModality::REQUERIMENTO);

        $this->assertSame('PL nº 001/'.now()->year, $first->official_number);
        $this->assertSame('PL nº 002/'.now()->year, $second->official_number);
        // Modalidade distinta recomeça a numeração.
        $this->assertSame('REQ nº 001/'.now()->year, $other->official_number);
    }

    public function test_proposition_with_executive_origin_uses_external_author(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin);

        $this->post(route('propositions.store'), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'origin' => PropositionOrigin::EXECUTIVO->value,
            'author_external_name' => 'Prefeito Municipal',
            'title' => 'PL oriundo do Executivo',
            'ementa' => 'Dispõe sobre matéria enviada pelo Executivo.',
        ])->assertRedirect();

        $proposition = Proposition::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame(PropositionOrigin::EXECUTIVO, $proposition->origin);
        $this->assertNull($proposition->author_councilor_id);
        $this->assertSame('Prefeito Municipal', $proposition->author_external_name);
        $this->assertSame('Prefeito Municipal', $proposition->authorName());
    }

    public function test_veto_modality_registers_with_own_abbreviation(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $service = app(PropositionService::class);

        $proposition = $service->create([
            'institution_id' => $institution->id,
            'modality' => PropositionModality::VETO->value,
            'origin' => PropositionOrigin::EXECUTIVO->value,
            'author_external_name' => 'Prefeito Municipal',
            'title' => 'Veto a projeto de lei',
            'ementa' => 'Veto total por inconstitucionalidade.',
        ], $admin);

        $registered = $service->registerOfficial($proposition, $admin);

        $this->assertSame(PropositionStatus::REGISTRADA, $registered->status);
        $this->assertSame('VET nº 001/'.now()->year, $registered->official_number);
    }

    public function test_legislative_origin_requires_councilor_author(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin);

        $this->post(route('propositions.store'), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'origin' => PropositionOrigin::LEGISLATIVO->value,
            'author_external_name' => 'Alguém externo',
            'title' => 'Sem vereador autor',
            'ementa' => 'Deveria exigir um vereador autor.',
        ])->assertSessionHasErrors('author_councilor_id');
    }

    public function test_external_origin_requires_author_name(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $councilor = $this->makeCouncilor($institution);

        $this->actingAs($admin);

        $this->post(route('propositions.store'), [
            'modality' => PropositionModality::PROJETO_LEI->value,
            'origin' => PropositionOrigin::EXECUTIVO->value,
            'author_councilor_id' => $councilor->id,
            'title' => 'Sem autor externo informado',
            'ementa' => 'Deveria exigir o nome do autor externo.',
        ])->assertSessionHasErrors('author_external_name');
    }

    private function register(
        PropositionService $service,
        Institution $institution,
        User $user,
        int $councilorId,
        PropositionModality $modality,
    ): Proposition {
        $proposition = $service->create([
            'institution_id' => $institution->id,
            'modality' => $modality->value,
            'author_councilor_id' => $councilorId,
            'title' => 'Proposição '.$modality->value,
            'ementa' => 'Ementa de teste.',
        ], $user);

        return $service->registerOfficial($proposition, $user);
    }
}
