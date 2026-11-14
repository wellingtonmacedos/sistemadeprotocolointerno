<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\LegislativeFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 1 — Estrutura legislativa: Comissões, composição histórica e fluxos versionados.
 */
class LegislativeStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_creates_commission_and_preserves_composition_history(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $antigo = $this->makeCouncilor($institution, ['name' => 'Vereador Antigo']);
        $novo = $this->makeCouncilor($institution, ['name' => 'Vereador Novo']);

        $this->actingAs($admin)->post(route('commissions.store'), [
            'name' => 'Constituição, Justiça e Redação',
            'acronym' => 'CCJ',
            'type' => 'permanente',
            'is_active' => '1',
        ])->assertRedirect();

        $commission = Commission::firstOrFail();
        $this->assertSame('CCJ', $commission->acronym);
        $this->assertSame($institution->id, $commission->institution_id);

        // Primeiro presidente.
        $this->actingAs($admin)->post(route('commissions.members.store', $commission), [
            'councilor_id' => $antigo->id,
            'role' => 'presidente',
            'start_date' => '2025-01-01',
        ])->assertRedirect();

        $this->assertSame($antigo->id, $commission->fresh()->president()->councilor_id);

        // Novo titular: o mandato anterior deve ser encerrado, não apagado.
        $this->actingAs($admin)->post(route('commissions.members.store', $commission), [
            'councilor_id' => $novo->id,
            'role' => 'presidente',
            'start_date' => '2026-01-01',
        ])->assertRedirect();

        $commission->refresh();
        $this->assertSame($novo->id, $commission->president()->councilor_id);
        $this->assertSame(2, $commission->members()->count());

        $anterior = $commission->members()->where('councilor_id', $antigo->id)->first();
        $this->assertNotNull($anterior->end_date);
        $this->assertFalse($anterior->isCurrent());
    }

    public function test_commission_is_isolated_between_institutions(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);

        $commissionB = Commission::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'name' => 'Comissão Exclusiva da Câmara B',
            'acronym' => 'CB',
            'type' => 'permanente',
            'is_active' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('commissions.show', $commissionB))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('commissions.index'))
            ->assertOk()
            ->assertDontSee('Comissão Exclusiva da Câmara B');
    }

    public function test_cannot_add_councilor_from_another_institution_to_composition(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $councilorB = $this->makeCouncilor($b, ['name' => 'Vereador da Câmara B']);

        $commission = Commission::withoutGlobalScopes()->create([
            'institution_id' => $a->id,
            'name' => 'Comissão A',
            'acronym' => 'CA',
            'type' => 'permanente',
            'is_active' => true,
        ]);

        $this->actingAs($adminA)->post(route('commissions.members.store', $commission), [
            'councilor_id' => $councilorB->id,
            'role' => 'membro',
            'start_date' => '2025-01-01',
        ])->assertSessionHasErrors('councilor_id');

        $this->assertSame(0, $commission->members()->count());
    }

    public function test_legislative_flow_is_created_with_default_steps_and_published(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo de Projeto de Lei',
            'modality' => 'projeto_lei',
        ])->assertRedirect();

        $flow = LegislativeFlow::firstOrFail();
        $version = $flow->versions()->firstOrFail();

        $this->assertSame('rascunho', $version->status);
        $this->assertGreaterThan(0, $version->steps()->count());

        $this->actingAs($admin)
            ->post(route('legislative-flows.publish', [$flow, $version]))
            ->assertRedirect();

        $version->refresh();
        $this->assertTrue($version->is_current);
        $this->assertSame('publicado', $version->status);
    }

    public function test_new_flow_version_does_not_affect_the_published_one(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo Requerimento',
            'modality' => 'requerimento',
        ])->assertRedirect();

        $flow = LegislativeFlow::firstOrFail();
        $v1 = $flow->versions()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('legislative-flows.publish', [$flow, $v1]))
            ->assertRedirect();

        // Fluxo simples (IND/REQ) tem menos etapas que o fluxo completo (PL).
        $this->assertLessThan(10, $v1->steps()->count());

        // Nova versão copia as etapas, mas a v1 continua vigente.
        $this->actingAs($admin)
            ->post(route('legislative-flows.versions.store', $flow))
            ->assertRedirect();

        $flow->refresh();
        $this->assertSame(2, $flow->versions()->count());
        $this->assertTrue($flow->versions()->where('version', 1)->firstOrFail()->is_current);
        $this->assertSame($v1->steps()->count(), $flow->versions()->where('version', 2)->firstOrFail()->steps()->count());
    }

    public function test_published_flow_version_is_immutable(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo PL',
            'modality' => 'projeto_lei',
        ])->assertRedirect();

        $flow = LegislativeFlow::firstOrFail();
        $version = $flow->versions()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('legislative-flows.publish', [$flow, $version]))
            ->assertRedirect();

        $before = $version->steps()->count();

        $this->actingAs($admin)
            ->put(route('legislative-flows.steps.update', [$flow, $version]), [
                'steps' => [
                    ['name' => 'Etapa alterada', 'step_type' => 'analise'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame($before, $version->fresh()->steps()->count());
    }

    public function test_updates_draft_steps_replacing_and_reordering(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo PL',
            'modality' => 'projeto_lei',
        ])->assertRedirect();

        $flow = LegislativeFlow::firstOrFail();
        $version = $flow->versions()->firstOrFail();
        $first = $version->steps()->orderBy('position')->first();

        $this->actingAs($admin)
            ->put(route('legislative-flows.steps.update', [$flow, $version]), [
                'steps' => [
                    ['id' => $first->id, 'name' => 'Recebimento renomeado', 'step_type' => 'recebimento', 'is_required' => '1'],
                    ['name' => 'Etapa nova', 'step_type' => 'outro'],
                ],
            ])
            ->assertRedirect();

        $version->refresh();
        $this->assertSame(2, $version->steps()->count());
        $this->assertSame('Recebimento renomeado', $version->steps()->orderBy('position')->first()->name);
    }

    public function test_institution_cannot_create_duplicate_flow_for_same_modality(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo PL',
            'modality' => 'projeto_lei',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('legislative-flows.store'), [
            'name' => 'Fluxo PL 2',
            'modality' => 'projeto_lei',
        ])->assertSessionHasErrors('modality');

        $this->assertSame(1, LegislativeFlow::count());
    }
}
