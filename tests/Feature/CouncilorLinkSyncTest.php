<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integridade do vínculo usuário ↔ vereador: os campos
 * `users.councilor_id` e `councilors.user_id` são espelhados e 1:1.
 */
class CouncilorLinkSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_linking_a_user_to_a_councilor_mirrors_on_the_councilor(): void
    {
        $institution = $this->makeInstitution();
        $councilor = $this->makeCouncilor($institution);

        $user = $this->makeUser($institution, 'presidente', ['councilor_id' => $councilor->id]);

        $this->assertSame($user->id, $councilor->fresh()->user_id);
        $this->assertSame($councilor->id, $user->fresh()->councilor_id);
    }

    public function test_linking_a_councilor_to_a_user_mirrors_on_the_user(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution, 'vereador');

        $councilor = $this->makeCouncilor($institution, ['user_id' => $user->id]);

        $this->assertSame($councilor->id, $user->fresh()->councilor_id);
        $this->assertSame($user->id, $councilor->fresh()->user_id);
    }

    public function test_relinking_moves_the_link_without_duplicates(): void
    {
        $institution = $this->makeInstitution();
        $councilor = $this->makeCouncilor($institution);
        $first = $this->makeUser($institution, 'vereador', ['councilor_id' => $councilor->id]);

        $second = $this->makeUser($institution, 'vereador');
        $second->update(['councilor_id' => $councilor->id]);

        $this->assertNull($first->fresh()->councilor_id);
        $this->assertSame($second->id, $councilor->fresh()->user_id);
        $this->assertSame($councilor->id, $second->fresh()->councilor_id);
    }

    public function test_unlinking_a_user_frees_the_councilor(): void
    {
        $institution = $this->makeInstitution();
        $councilor = $this->makeCouncilor($institution);
        $user = $this->makeUser($institution, 'vereador', ['councilor_id' => $councilor->id]);

        $user->update(['councilor_id' => null]);

        $this->assertNull($councilor->fresh()->user_id);
        $this->assertNull($user->fresh()->councilor_id);
    }

    public function test_unlinking_a_councilor_frees_the_user(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution, 'vereador');
        $councilor = $this->makeCouncilor($institution, ['user_id' => $user->id]);

        $councilor->update(['user_id' => null]);

        $this->assertNull($user->fresh()->councilor_id);
        $this->assertNull($councilor->fresh()->user_id);
    }
}
