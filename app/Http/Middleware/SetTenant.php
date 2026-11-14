<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a instituição (Câmara) da sessão e aplica o isolamento multi-Câmaras.
 * O Super Admin opera globalmente (sem tenant definido).
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->is_active) {
                auth()->logout();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Sua conta está bloqueada. Procure o administrador.']);
            }

            if (! $user->isSuperAdmin() && $user->institution) {
                Tenant::set($user->institution);
            }
        }

        return $next($request);
    }
}
