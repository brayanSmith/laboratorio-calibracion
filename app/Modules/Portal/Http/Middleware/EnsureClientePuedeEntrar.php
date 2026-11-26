<?php

namespace App\Modules\Portal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del portal al cliente cuyo acceso se deshabilitó (o cuyo laboratorio se desactivó)
 * mientras tenía la sesión abierta: el acceso se revisa en cada petición, no solo al entrar.
 */
class EnsureClientePuedeEntrar
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = Auth::guard('cliente')->user();

        if ($cliente === null || ! $cliente->puedeEntrarAlPortal()) {
            Auth::guard('cliente')->logout();
            $request->session()->regenerateToken();

            return to_route('portal.login')->withErrors([
                'portal_usuario' => __('Tu acceso al portal no está habilitado. Comunícate con el laboratorio.'),
            ]);
        }

        return $next($request);
    }
}
