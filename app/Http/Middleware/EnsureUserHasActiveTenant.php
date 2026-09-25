<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasActiveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_platform_admin) {
            return to_route('plataforma.dashboard');
        }

        abort_unless(
            $request->user()?->tenant?->activo,
            403,
            __('Tu cuenta no está asociada a un tenant activo.'),
        );

        return $next($request);
    }
}
