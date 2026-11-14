<?php

namespace Tests\Feature;

use App\Enums\PropositionStatus;
use App\Models\Proposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Experiência de vereador": disponível para o perfil Vereador e também para
 * qualquer perfil vinculado a um registro de vereador (ex.: Presidente).
 */
class CouncilorExperienceTest extends TestCase
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
            'title' => 'Proposição de teste',
            'ementa' => 'Ementa de teste',
            'status' => PropositionStatus::APROVADA->value,
        ], $attributes));
    }

    public function test_president_linked_to_councilor_gets_councilor_experience(): void
    {
        $institution = $this->makeInstitution();
        $mine = $this->makeCouncilor($institution);
        $other = $this->makeCouncilor($institution);

        $ownProposition = $this->makeProposition($institution, ['author_councilor_id' => $mine->id]);
        $otherProposition = $this->makeProposition($institution, ['author_councilor_id' => $other->id]);

        $president = $this->makeUser($institution, 'presidente', ['councilor_id' => $mine->id]);

        $dashboard = $this->actingAs($president)->get(route('councilor.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Painel do Vereador');

        $list = $this->actingAs($president)->get(route('councilor.propositions'));
        $list->assertOk();

        $ids = collect($list->viewData('propositions')->items())->pluck('id');
        $this->assertTrue($ids->contains($ownProposition->id));
        $this->assertFalse($ids->contains($otherProposition->id));
    }

    public function test_president_without_councilor_link_does_not_get_councilor_experience(): void
    {
        $institution = $this->makeInstitution();
        $president = $this->makeUser($institution, 'presidente');

        $response = $this->actingAs($president)->get(route('painel'));
        $response->assertOk();
        $response->assertDontSee('Painel do Vereador');
    }
}
