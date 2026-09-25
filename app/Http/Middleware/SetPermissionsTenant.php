<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsTenant
{
    /**
     * Scope role and permission checks to the authenticated user's tenant.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($request->user()?->tenant_id);

        return $next($request);
    }
}
