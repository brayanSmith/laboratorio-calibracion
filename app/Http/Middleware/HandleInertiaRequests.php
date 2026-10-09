<?php

namespace App\Http\Middleware;

use App\Enums\TenantPermission;
use App\Models\Calibracion;
use App\Models\Despacho;
use App\Models\Empresa;
use App\Models\Ingreso;
use App\Models\Mantenimiento;
use App\Models\ServicioTercero;
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
            'pendientes' => fn () => $this->pendientes($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get the trabajos que siguen pendientes (sin finalizar), para los badges del menú:
     * ingresos por recibir, mantenimientos y calibraciones, locales (aún sin terminar) y de tercero (servicio
     * sin estado final), y los despachos cuya entrega no se ha recibido. Cada grupo es
     * null si el usuario no tiene permiso para verlo.
     *
     * @return array{
     *     mantenimientos: array{local: int, tercero: int}|null,
     *     calibraciones: array{local: int, tercero: int}|null,
     *     despachos: int|null,
     *     ingresos: int|null,
     * }|null
     */
    private function pendientes(?User $user): ?array
    {
        if (! $user?->tenant_id) {
            return null;
        }

        $tenantId = $user->tenant_id;

        $tercerosPendientes = fn (string $tipoServicio): int => ServicioTercero::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', $tipoServicio)
            ->whereNull('estado_final_equipo')
            ->count();

        return [
            'mantenimientos' => $user->can(TenantPermission::MantenimientosVer->value) ? [
                'local' => Mantenimiento::query()
                    ->where('tenant_id', $tenantId)
                    ->where('estado_mantenimiento', '!=', 'FINALIZADO')
                    ->count(),
                'tercero' => $tercerosPendientes('MANTENIMIENTO'),
            ] : null,
            'calibraciones' => $user->can(TenantPermission::CalibracionesVer->value) ? [
                'local' => Calibracion::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('estado_calibracion', ['PENDIENTE', 'EN_PROCESO'])
                    ->count(),
                'tercero' => $tercerosPendientes('CALIBRACION'),
            ] : null,
            'despachos' => $user->can(TenantPermission::DespachosVer->value)
                ? Despacho::query()
                    ->where('tenant_id', $tenantId)
                    ->where('entrega_recibida', false)
                    ->count()
                : null,
            'ingresos' => $user->can(TenantPermission::IngresosVer->value)
                ? Ingreso::query()
                    ->where('tenant_id', $tenantId)
                    ->where('estado_ingreso', 'PENDIENTE')
                    ->count()
                : null,
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
