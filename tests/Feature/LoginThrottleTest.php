<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * B3 — proteção contra tentativas de login (throttle).
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $institution = $this->makeInstitution();
        $this->makeUser($institution, 'administrador', ['email' => 'usuario@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.attempt'), [
                'email' => 'usuario@example.test',
                'password' => 'senha-errada',
            ])->assertSessionHasErrors('email');
        }

        $key = 'login:usuario@example.test|127.0.0.1';
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        $response = $this->post(route('login.attempt'), [
            'email' => 'usuario@example.test',
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_throttle_is_cleared_after_a_successful_login(): void
    {
        $institution = $this->makeInstitution();
        $this->makeUser($institution, 'administrador', [
            'email' => 'usuario@example.test',
            'password' => 'Senha@12345',
        ]);

        $key = 'login:usuario@example.test|127.0.0.1';

        $this->post(route('login.attempt'), [
            'email' => 'usuario@example.test',
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $this->assertGreaterThan(0, RateLimiter::attempts($key));

        $this->post(route('login.attempt'), [
            'email' => 'usuario@example.test',
            'password' => 'Senha@12345',
        ])->assertRedirect(route('painel'));

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts($key));
    }
}
