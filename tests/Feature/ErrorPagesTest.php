<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C1/C2 — páginas de erro customizadas e health check.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // As páginas de erro só são exibidas com o debug desativado.
        config(['app.debug' => false]);
    }

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_not_found_page_is_customized(): void
    {
        $this->get('/rota-inexistente')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertSee('Desenvolvido por W-Tech Solutions');
    }

    public function test_forbidden_page_is_customized(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution, 'vereador');

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('Acesso negado');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
