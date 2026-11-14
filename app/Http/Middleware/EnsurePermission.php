<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o usuário possui ao menos uma das permissões informadas.
 * Uso: ->middleware('permission:protocols.create,protocols.edit')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);
        abort_unless($user->hasAnyPermission($permissions), 403, 'Acesso não autorizado para esta operação.');

        return $next($request);
    }
}
