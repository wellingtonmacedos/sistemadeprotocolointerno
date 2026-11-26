<?php

namespace Tests\Browser;

use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Fluxos E2E de autenticação.
 *
 * Requer um servidor de aplicação em execução (APP_URL) e a base de dados
 * semeada com os dados de demonstração (veja o README, seção "Testes").
 */
class AuthTest extends DuskTestCase
{
    public function test_a_tela_de_login_esta_acessivel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->asGuest($browser)
                ->visit('/login')
                ->assertSee('Acessar o sistema')
                ->assertSee('Entrar')
                ->assertSee('Esqueci minha senha');
        });
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->browse(function (Browser $browser) {
            $this->asGuest($browser)
                ->visit('/painel')
                ->waitForLocation('/login')
                ->assertPathIs('/login');
        });
    }

    public function test_usuario_entra_com_credenciais_validas(): void
    {
        $this->browse(function (Browser $browser) {
            $this->asGuest($browser)
                ->visit('/login')
                ->type('email', 'admin@camarademo.local')
                ->type('password', 'Senha@12345')
                ->press('Entrar')
                ->waitForLocation('/painel')
                ->assertPathIs('/painel')
                ->assertSee('Visão geral dos protocolos e proposições legislativas');
        });
    }

    public function test_login_falha_com_credenciais_invalidas(): void
    {
        // E-mail único por execução para não esbarrar no rate limiter (login:email|ip).
        $email = 'inexistente-'.Str::lower(Str::random(8)).'@exemplo.com';

        $this->browse(function (Browser $browser) use ($email) {
            $this->asGuest($browser)
                ->visit('/login')
                ->type('email', $email)
                ->type('password', 'senha-incorreta')
                ->press('Entrar')
                ->waitForText('Credenciais inválidas.')
                ->assertPathIs('/login');
        });
    }

    /**
     * O Dusk reutiliza o mesmo browser entre os testes da classe; limpar os
     * cookies garante que cada teste comece como visitante (deslogado).
     */
    private function asGuest(Browser $browser): Browser
    {
        $browser->driver->manage()->deleteAllCookies();

        return $browser;
    }
}
