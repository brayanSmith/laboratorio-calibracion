<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => fn () => $user?->tenant_id
                    ? $user->getAllPermissions()->pluck('name')->sort()->values()
                    : [],
            ],
            'empresa' => fn () => $this->brand($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get the name and logo the interface shows in place of the app branding.
     *
     * @return array{nombre: string, logo_url: string|null}|null
     */
    private function brand(?User $user): ?array
    {
        if (! $user?->tenant_id) {
            return null;
        }

        $empresa = Empresa::query()->where('tenant_id', $user->tenant_id)->first();

        return $empresa ? ['nombre' => $empresa->nombre, 'logo_url' => $empresa->logoUrl()] : null;
    }
}
