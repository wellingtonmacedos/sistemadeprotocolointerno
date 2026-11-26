<?php

namespace Tests\Feature;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * B1 — recuperação de senha (link por e-mail e redefinição).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_link_is_sent_to_a_known_user(): void
    {
        Notification::fake();

        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_is_not_sent_for_unknown_email(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nao-existe@example.test'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_link_is_not_sent_when_email_is_invalid(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nao-e-email'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NovaSenha@2026', $user->fresh()->password));
    }

    public function test_password_is_not_reset_with_an_invalid_token(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $this->post(route('password.store'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Senha@12345', $user->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $institution = $this->makeInstitution();
        $user = $this->makeUser($institution);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'OutraSenha@2026',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Senha@12345', $user->fresh()->password));
    }
}
