<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-022 / RN-014 / LIM-022 / CA-SEG-05:
 * en producción, ADMIN/SUPERVISOR (y cualquier cuenta con mfa_habilitado)
 * deben tener TOTP enrolado; si no, quedan retenidos en el enrolamiento
 * hasta completarlo (o recibir reset de otro Admin, LIM-022).
 * En desarrollo/staging es un no-op (RN-014: obligatorio solo producción).
 */
class EnforceMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $conMfa = $user->mfa_habilitado && filled($user->mfa_secret);
        $requiere = $user->requiereMfa() || $user->mfa_habilitado;

        if (! app()->isProduction() || $conMfa || ! $requiere) {
            return $next($request);
        }

        // sólo puede: enrolamiento, mantener sesión/cambiar tema, logout
        $permitidas = ['mfa.setup', 'mfa.guardar', 'logout', 'verify-email', 'filament.*.auth.logout', 'filament.*.auth.logging-out'];

        if (! $request->routeIs($permitidas)) {
            return redirect()->route('mfa.setup');
        }

        return $next($request);
    }
}
