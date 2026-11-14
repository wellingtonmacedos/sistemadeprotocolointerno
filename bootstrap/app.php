<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenant::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/painel');

        // Garante que o tenant (Câmara) seja resolvido ANTES do binding de rotas,
        // para que o escopo global de institution_id seja aplicado na resolução do
        // modelo (evita acesso cruzado entre Câmaras via URL direta).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
