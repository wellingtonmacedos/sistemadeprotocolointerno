<?php

namespace Tests\Feature;

use App\Enums\AgendaItemStatus;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\LegislativeSession;
use App\Models\PostVotingAction;
use App\Models\Proposition;
use App\Models\QuorumSetting;
use App\Models\SessionAgendaItem;
use App\Models\Voting;
use App\Models\VotingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 — Votações, apuração por quórum e encaminhamentos pós-votação.
 *
 * Regra central preservada: a inclusão na pauta NÃO aprova a matéria; apenas o
 * resultado formal da votação, validado pelo quórum configurado, altera a
 * situação da proposição.
 */
class VotingTest extends TestCase
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

    protected function addAgendaItem($institution, $session, $proposition): SessionAgendaItem
    {
        return SessionAgendaItem::withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'session_id' => $session->id,
            'proposition_id' => $proposition->id,
            'position' => 1,
            'appreciation_type' => 'discussao_e_votacao',
            'will_discuss' => true,
            'will_vote' => true,
            'status' => AgendaItemStatus::PAUTADA,
        ]);
    }

    public function test_registers_a_nominal_voting_and_computes_counts(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'nominal',
            'turno' => 1,
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($institution->id, $voting->institution_id);
        $this->assertSame($item->id, $voting->session_agenda_item_id);

        $votes = ['favoravel', 'favoravel', 'favoravel', 'contrario', 'abstencao', 'ausente'];
        foreach ($votes as $vote) {
            $this->actingAs($admin)->post(route('sessions.votings.votes.store', $voting), [
                'councilor_id' => $this->makeCouncilor($institution)->id,
                'vote' => $vote,
            ])->assertRedirect();
        }

        $voting->refresh();
        $this->assertSame(3, $voting->votes_favor);
        $this->assertSame(1, $voting->votes_against);
        $this->assertSame(1, $voting->votes_abstention);
        $this->assertSame(1, $voting->votes_absent);
        // Presentes excluem ausentes: 3 + 1 + 1 = 5.
        $this->assertSame(5, $voting->present_count);
    }

    public function test_finalizes_an_approved_voting_and_updates_statuses(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'nominal',
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();

        foreach (['favoravel', 'favoravel', 'favoravel', 'contrario'] as $vote) {
            $this->actingAs($admin)->post(route('sessions.votings.votes.store', $voting), [
                'councilor_id' => $this->makeCouncilor($institution)->id,
                'vote' => $vote,
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('sessions.votings.finalize', $voting), [
            'result' => 'aprovada',
        ])->assertRedirect();

        $voting->refresh();
        $this->assertSame('aprovada', $voting->result->value);
        $this->assertNotNull($voting->result_registered_at);
        $this->assertSame('aprovada', $proposition->fresh()->status->value);
        $this->assertSame(AgendaItemStatus::APROVADA, $item->fresh()->status);
    }

    public function test_registers_a_symbolic_voting_without_individual_votes(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'simbolica',
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();

        // Votação simbólica não exige votos individuais nem checagem de quórum.
        $this->actingAs($admin)->post(route('sessions.votings.finalize', $voting), [
            'result' => 'aprovada',
        ])->assertRedirect();

        $this->assertSame('aprovada', $voting->fresh()->result->value);
        $this->assertSame('aprovada', $proposition->fresh()->status->value);
    }

    public function test_configured_quorum_blocks_an_incompatible_result(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        // Regra específica da modalidade: maioria absoluta com 5 presentes.
        QuorumSetting::persist($institution->id, 'projeto_lei', [
            'rule' => 'maioria_absoluta',
            'min_presence' => 5,
        ]);

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'nominal',
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('maioria_absoluta', $voting->quorum_rule->value);
        $this->assertSame(5, $voting->quorum_min_presence);

        foreach (['favoravel', 'favoravel', 'favoravel'] as $vote) {
            $this->actingAs($admin)->post(route('sessions.votings.votes.store', $voting), [
                'councilor_id' => $this->makeCouncilor($institution)->id,
                'vote' => $vote,
            ])->assertRedirect();
        }

        // Quórum mínimo (5) não atingido: aprovação é recusada.
        $this->actingAs($admin)->post(route('sessions.votings.finalize', $voting), [
            'result' => 'aprovada',
        ])->assertSessionHasErrors('result');

        $this->assertNull($voting->fresh()->result);
        $this->assertSame('em_analise', $proposition->fresh()->status->value);

        // Completando o quórum, a aprovação passa a ser compatível.
        foreach (['favoravel', 'favoravel'] as $vote) {
            $this->actingAs($admin)->post(route('sessions.votings.votes.store', $voting), [
                'councilor_id' => $this->makeCouncilor($institution)->id,
                'vote' => $vote,
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('sessions.votings.finalize', $voting), [
            'result' => 'aprovada',
        ])->assertRedirect();

        $this->assertSame('aprovada', $voting->fresh()->result->value);
        $this->assertSame('aprovada', $proposition->fresh()->status->value);
    }

    public function test_registers_a_rejected_voting(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'nominal',
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();

        foreach (['favoravel', 'contrario', 'contrario'] as $vote) {
            $this->actingAs($admin)->post(route('sessions.votings.votes.store', $voting), [
                'councilor_id' => $this->makeCouncilor($institution)->id,
                'vote' => $vote,
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('sessions.votings.finalize', $voting), [
            'result' => 'rejeitada',
        ])->assertRedirect();

        $this->assertSame('rejeitada', $voting->fresh()->result->value);
        $this->assertSame('rejeitada', $proposition->fresh()->status->value);
        $this->assertSame(AgendaItemStatus::REJEITADA, $item->fresh()->status);
    }

    public function test_prevents_duplicate_voting_for_the_same_item_and_turno(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $proposition = $this->makeProposition($institution);
        $item = $this->addAgendaItem($institution, $session, $proposition);

        $payload = ['session_agenda_item_id' => $item->id, 'type' => 'nominal', 'turno' => 1];

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), $payload)->assertRedirect();

        $this->actingAs($admin)
            ->post(route('sessions.votings.store', $session), $payload)
            ->assertSessionHasErrors('session_agenda_item_id');

        $this->assertSame(1, Voting::withoutGlobalScopes()->count());

        // Um novo turno é permitido para o mesmo item.
        $this->actingAs($admin)
            ->post(route('sessions.votings.store', $session), [...$payload, 'turno' => 2])
            ->assertRedirect();

        $this->assertSame(2, Voting::withoutGlobalScopes()->count());
    }

    public function test_cannot_register_a_voting_for_an_item_of_another_session(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $sessionA = $this->makeSession($institution, ['number' => 1]);
        $sessionB = $this->makeSession($institution, ['number' => 2, 'date' => '2026-03-11']);
        $itemB = $this->addAgendaItem($institution, $sessionB, $this->makeProposition($institution));

        $this->actingAs($admin)->post(route('sessions.votings.store', $sessionA), [
            'session_agenda_item_id' => $itemB->id,
            'type' => 'nominal',
        ])->assertSessionHasErrors('session_agenda_item_id');

        $this->assertSame(0, Voting::withoutGlobalScopes()->count());
    }

    public function test_removing_a_voting_requires_justification(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $session = $this->makeSession($institution);
        $item = $this->addAgendaItem($institution, $session, $this->makeProposition($institution));

        $this->actingAs($admin)->post(route('sessions.votings.store', $session), [
            'session_agenda_item_id' => $item->id,
            'type' => 'nominal',
        ])->assertRedirect();

        $voting = Voting::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('sessions.votings.destroy', $voting), ['justification' => ''])
            ->assertSessionHasErrors('justification');

        $this->assertNull($voting->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('sessions.votings.destroy', $voting), ['justification' => 'Registro indevido'])
            ->assertRedirect();

        $this->assertSoftDeleted('votings', ['id' => $voting->id]);
    }

    public function test_registers_and_removes_a_post_voting_action(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $proposition = $this->makeProposition($institution, ['status' => 'aprovada']);

        $this->actingAs($admin)->post(route('propositions.post_voting.store', $proposition), [
            'status' => 'aguardando_redacao_final',
            'notes' => 'Encaminhada à redação final.',
        ])->assertRedirect();

        $action = PostVotingAction::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('aguardando_redacao_final', $action->status->value);
        $this->assertSame($admin->id, $action->performed_by);
        // Situações não terminais não alteram a situação da proposição.
        $this->assertSame('aprovada', $proposition->fresh()->status->value);

        $this->actingAs($admin)
            ->delete(route('propositions.post_voting.destroy', $action), ['justification' => 'Lançamento equivocado'])
            ->assertRedirect();

        $this->assertSoftDeleted('post_voting_actions', ['id' => $action->id]);
    }

    public function test_archiving_a_post_voting_action_updates_proposition_status(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $proposition = $this->makeProposition($institution, ['status' => 'aprovada']);

        $this->actingAs($admin)->post(route('propositions.post_voting.store', $proposition), [
            'status' => 'arquivada',
        ])->assertRedirect();

        $this->assertSame('arquivada', $proposition->fresh()->status->value);
    }

    public function test_proposition_show_lists_votings(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);
        $proposition = $this->makeProposition($institution);

        $this->actingAs($admin)
            ->get(route('propositions.show', $proposition))
            ->assertOk()
            ->assertSee('Votações em Plenário')
            ->assertSee('Encaminhamentos pós-votação');
    }

    public function test_cannot_manage_votings_of_another_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $sessionB = $this->makeSession($b);
        $itemB = $this->addAgendaItem($b, $sessionB, $this->makeProposition($b));

        // Sessão de outra instituição não é acessível (escopo global).
        $this->actingAs($adminA)->post(route('sessions.votings.store', $sessionB), [
            'session_agenda_item_id' => $itemB->id,
            'type' => 'nominal',
        ])->assertNotFound();

        $this->assertSame(0, Voting::withoutGlobalScopes()->count());
    }

    public function test_cannot_record_a_vote_on_a_voting_of_another_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();
        $adminA = $this->makeUser($a);
        $sessionB = $this->makeSession($b);
        $propositionB = $this->makeProposition($b);
        $itemB = $this->addAgendaItem($b, $sessionB, $propositionB);

        $votingB = Voting::withoutGlobalScopes()->create([
            'institution_id' => $b->id,
            'session_id' => $sessionB->id,
            'session_agenda_item_id' => $itemB->id,
            'proposition_id' => $propositionB->id,
            'turno' => 1,
            'type' => 'nominal',
        ]);

        $this->actingAs($adminA)->post(route('sessions.votings.votes.store', $votingB), [
            'councilor_id' => $this->makeCouncilor($b)->id,
            'vote' => 'favoravel',
        ])->assertNotFound();

        $this->assertSame(0, VotingRecord::withoutGlobalScopes()->count());
    }

    public function test_viewer_cannot_register_votings(): void
    {
        $institution = $this->makeInstitution();
        $councilorUser = $this->makeUser($institution, 'vereador');
        $session = $this->makeSession($institution);
        $item = $this->addAgendaItem($institution, $session, $this->makeProposition($institution));

        $this->actingAs($councilorUser)
            ->post(route('sessions.votings.store', $session), [
                'session_agenda_item_id' => $item->id,
                'type' => 'nominal',
            ])->assertForbidden();

        $this->assertSame(0, Voting::withoutGlobalScopes()->count());
    }

    public function test_user_without_quorum_permission_is_forbidden(): void
    {
        $institution = $this->makeInstitution();
        $councilorUser = $this->makeUser($institution, 'vereador');

        $this->actingAs($councilorUser)->get(route('quorum.index'))->assertForbidden();
    }

    public function test_managing_quorum_settings(): void
    {
        $institution = $this->makeInstitution();
        $admin = $this->makeUser($institution);

        $this->actingAs($admin)->get(route('quorum.index'))->assertOk();

        $this->actingAs($admin)->post(route('quorum.store'), [
            'modality' => 'projeto_lei',
            'rule' => 'dois_tercos',
            'min_presence' => 7,
        ])->assertRedirect();

        $setting = QuorumSetting::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($institution->id, $setting->institution_id);
        $this->assertSame('dois_tercos', $setting->rule->value);
        $this->assertSame(7, $setting->min_presence);

        // Reenviar a mesma modalidade atualiza em vez de duplicar (scope_key único).
        $this->actingAs($admin)->post(route('quorum.store'), [
            'modality' => 'projeto_lei',
            'rule' => 'tres_quintos',
        ])->assertRedirect();

        $this->assertSame(1, QuorumSetting::withoutGlobalScopes()->count());
        $this->assertSame('tres_quintos', $setting->fresh()->rule->value);
    }
}
