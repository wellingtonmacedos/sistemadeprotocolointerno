<?php

namespace Tests\Feature;

use App\Enums\AgendaItemStatus;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\LegislativeSession;
use App\Models\Proposition;
use App\Models\SessionAgendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 3 — Sessões legislativas e pauta (ordem do dia).
 */
class LegislativeSessionTest extends TestCase
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
            'status' => 'em_analise',
        ], $attributes));
    }

    protected function makeSession($institution, array $attributes = []): LegislativeSession
    {
        return LegislativeSession::withoutGlobalScopes()->create(array_merge([
            'institution_id' => $institution->id,
            'number' => 1,
            'type' => SessionType::ORDINARIA,
            'date' => '2026-03-10',
            'status' => SessionStatus::AGENDADA,
        ], $attributes));
    }

    protected function sessionPayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 1,
            'type' => 'ordinaria',
            'date' => '2026-03-10',
            'time' => '19:00',
            'location' => 'Plenário',
            'status' => 'agendada',
        ], $overrides);
    }

    public function test_creates_a_legislative_session(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $president = $this->makeCouncilor($institution);

        $this->actingAs($admin)
            ->post(route('sessions.store'), $this->sessionPayload(['president_councilor_id' => $president->id]))
            ->assertRedirect();

        $session = LegislativeSession::firstOrFail();
        $this->assertSame($institution->id, $session->institution_id);
        $this->assertSame(1, $session->number);
        $this->assertSame(SessionType::ORDINARIA, $session->type);
        $this->assertSame(SessionStatus::AGENDADA, $session->status);
        $this->assertSame($president->id, $session->president_councilor_id);
    }

    public function test_prevents_duplicate_session_number_in_the_same_year(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('sessions.store'), $this->sessionPayload())->assertRedirect();

        $this->actingAs($admin)
            ->post(route('sessions.store'), $this->sessionPayload(['date' => '2026-08-20']))
            ->assertSessionHasErrors('number');

        $this->assertSame(1, LegislativeSession::withoutGlobalScopes()->count());
    }

    public function test_allows_same_number_in_a_different_year(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->post(route('sessions.store'), $this->sessionPayload(['date' => '2026-03-10']))->assertRedirect();
        $this->actingAs($admin)->post(route('sessions.store'), $this->sessionPayload(['date' => '2027-03-10']))->assertRedirect();

        $this->assertSame(2, LegislativeSession::withoutGlobalScopes()->count());
    }

    public function test_adds_propositions_to_the_agenda_with_sequential_positions(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $p1 = $this->makeProposition($institution);
        $p2 = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $p1->id,
            'appreciation_type' => 'discussao_e_votacao',
            'will_discuss' => 1,
            'will_vote' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $p2->id,
            'appreciation_type' => 'leitura',
        ])->assertRedirect();

        $items = SessionAgendaItem::withoutGlobalScopes()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame([1, 2], $items->pluck('position')->all());
        $this->assertSame(AgendaItemStatus::PAUTADA, $items->first()->status);
        $this->assertSame($institution->id, $items->first()->institution_id);
    }

    public function test_cannot_add_the_same_proposition_twice_to_the_agenda(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $proposition->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $proposition->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertSessionHasErrors('proposition_id');

        $this->assertSame(1, SessionAgendaItem::withoutGlobalScopes()->count());
    }

    public function test_scheduling_a_proposition_does_not_change_its_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution, ['status' => 'em_analise']);

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $proposition->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertRedirect();

        // Estar na pauta não equivale a aprovação: a situação da proposição permanece intacta.
        $this->assertSame('em_analise', $proposition->fresh()->status->value);
    }

    public function test_reorders_agenda_items(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
                'proposition_id' => $this->makeProposition($institution)->id,
                'appreciation_type' => 'discussao_e_votacao',
            ])->assertRedirect();
        }

        $last = SessionAgendaItem::withoutGlobalScopes()->orderByDesc('position')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('sessions.agenda.move', $last), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(2, $last->fresh()->position);
    }

    public function test_updates_agenda_item_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $proposition->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertRedirect();

        $item = SessionAgendaItem::firstOrFail();

        $this->actingAs($admin)->post(route('sessions.agenda.status', $item), [
            'status' => 'em_votacao',
        ])->assertRedirect();

        $this->assertSame(AgendaItemStatus::EM_VOTACAO, $item->fresh()->status);
    }

    public function test_removing_an_agenda_item_requires_justification_and_reorders(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
                'proposition_id' => $this->makeProposition($institution)->id,
                'appreciation_type' => 'discussao_e_votacao',
            ])->assertRedirect();
        }

        $first = SessionAgendaItem::withoutGlobalScopes()->orderBy('position')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('sessions.agenda.destroy', $first), ['justification' => ''])
            ->assertSessionHasErrors('justification');

        $this->assertNull($first->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('sessions.agenda.destroy', $first), ['justification' => 'Retirada a pedido do autor'])
            ->assertRedirect();

        $this->assertSoftDeleted('session_agenda_items', ['id' => $first->id]);

        // As posições restantes são reordenadas de forma contínua (1, 2).
        $remaining = SessionAgendaItem::query()
            ->withoutGlobalScope('institution')
            ->orderBy('position')
            ->pluck('position')
            ->all();
        $this->assertSame([1, 2], $remaining);
    }

    public function test_cannot_access_sessions_of_another_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $sessionB = $this->makeSession($b);

        $this->actingAs($adminA)
            ->get(route('sessions.show', $sessionB))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('sessions.destroy', $sessionB), ['justification' => 'Tentativa indevida'])
            ->assertNotFound();
    }

    public function test_cannot_add_a_proposition_from_another_institution_to_the_agenda(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $sessionA = $this->makeSession($a);
        $propositionB = $this->makeProposition($b);

        $this->actingAs($adminA)->post(route('sessions.agenda.store', $sessionA), [
            'proposition_id' => $propositionB->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertSessionHasErrors('proposition_id');

        $this->assertSame(0, SessionAgendaItem::withoutGlobalScopes()->count());
    }

    public function test_proposition_show_lists_the_sessions_it_was_scheduled_in(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)->post(route('sessions.agenda.store', $session), [
            'proposition_id' => $proposition->id,
            'appreciation_type' => 'discussao_e_votacao',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('propositions.show', $proposition))
            ->assertOk()
            ->assertSee('Sessões em que a matéria foi pautada');
    }

    public function test_viewer_cannot_manage_sessions(): void
    {
        $institution = $this->makeInstitution();
        $councilorUser = $this->makeUser($institution, 'vereador');

        $this->actingAs($councilorUser)->get(route('sessions.index'))->assertOk();

        $this->actingAs($councilorUser)
            ->post(route('sessions.store'), $this->sessionPayload())
            ->assertForbidden();

        $this->assertSame(0, LegislativeSession::withoutGlobalScopes()->count());
    }

    public function test_user_without_session_permission_is_forbidden(): void
    {
        $institution = $this->makeInstitution();
        $protocolUser = $this->makeUser($institution, 'recepcao_protocolo');

        $this->actingAs($protocolUser)->get(route('sessions.index'))->assertForbidden();
    }
}
