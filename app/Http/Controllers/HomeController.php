<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /** Redireciona para o painel (autenticado) ou para o login. */
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route('painel')
            : redirect()->route('login');
    }
}
