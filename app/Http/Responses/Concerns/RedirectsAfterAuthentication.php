<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

trait RedirectsAfterAuthentication
{
    /**
     * Get the path the user lands on after authenticating. Platform administrators have their own panel.
     */
    protected function redirectPathAfterAuthentication(Request $request, string $redirect): string
    {
        if ($request->user()?->is_platform_admin) {
            return route('plataforma.dashboard', absolute: false);
        }

        return $redirect;
    }
}
