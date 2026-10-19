<?php

namespace App\Http\Middleware;

use App\Enums\TenantPermission;
use App\Models\AjustePlataforma;
use App\Models\Empresa;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
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
            'plataforma' => fn () => $this->plataforma(),
            'empresa' => fn () => $this->brand($user),
            'pendientes' => fn () => $this->pendientes($user),
            'porAgendar' => fn () => $this->porAgendar($user),
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
                    ->visiblesPara($user)
                    ->where('estado_mantenimiento', '!=', 'FINALIZADO')
                    ->count(),
                'tercero' => $tercerosPendientes('MANTENIMIENTO'),
            ] : null,
            'calibraciones' => $user->can(TenantPermission::CalibracionesVer->value) ? [
                'local' => Calibracion::query()
                    ->where('tenant_id', $tenantId)
                    ->visiblesPara($user)
                    ->whereIn('estado_calibracion', ['PENDIENTE', 'EN_PROCESO'])
                    ->count(),
                'tercero' => $tercerosPendientes('CALIBRACION'),
            ] : null,
            'despachos' => $user->can(TenantPermission::DespachosVer->value)
                ? Despacho::query()
                    ->where('tenant_id', $tenantId)
                    ->visiblesPara($user)
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
     * Cuántos mantenimientos, calibraciones y despachos están listos para agendarse, para
     * los atajos del menú superior (ver OrdenTrabajoController::equiposListos(),
     * equiposListosCalibracion() y despachosListos()). Cada contador es null si el usuario
     * no tiene el permiso de agendar ese servicio; todo es null si no tiene ninguno.
     *
     * @return array{mantenimiento: int|null, calibracion: int|null, despachos: int|null}|null
     */
    private function porAgendar(?User $user): ?array
    {
        if (! $user?->tenant_id) {
            return null;
        }

        $tenantId = $user->tenant_id;
        $puedeMantenimiento = $user->can(TenantPermission::AgendarMantenimiento->value);
        $puedeCalibracion = $user->can(TenantPermission::AgendarCalibracion->value);
        $puedeDespachos = $user->can(TenantPermission::AgendarDespachos->value);

        if (! $puedeMantenimiento && ! $puedeCalibracion && ! $puedeDespachos) {
            return null;
        }

        return [
            'mantenimiento' => $puedeMantenimiento ? EquipoProgramacion::query()->listosParaMantenimiento($tenantId)->count() : null,
            'calibracion' => $puedeCalibracion ? OrdenTrabajo::query()->listosParaCalibracion($tenantId)->count() : null,
            'despachos' => $puedeDespachos ? Despacho::query()->listosParaAgendar($tenantId)->count() : null,
        ];
    }

    /**
     * Public identity of the platform, shown to guests on the welcome page.
     *
     * @return array{nombre: string, logo_url: string|null}
     */
    private function plataforma(): array
    {
        $ajuste = AjustePlataforma::query()->first();

        return [
            'nombre' => $ajuste?->nombre ?: config('app.name'),
            'logo_url' => $ajuste?->logoUrl(),
        ];
    }

    /**
     * Get the name, logo and colors the interface shows in place of the app branding.
     *
     * @return array{nombre: string, logo_url: string|null, color_tema: string|null, color_nav: string|null}|null
     */
    private function brand(?User $user): ?array
    {
        if (! $user?->tenant_id) {
            return null;
        }

        $empresa = Empresa::query()->where('tenant_id', $user->tenant_id)->first();

        return $empresa ? [
            'nombre' => $empresa->nombre,
            'logo_url' => $empresa->logoUrl(),
            'color_tema' => $empresa->color_tema,
            'color_nav' => $empresa->color_nav,
        ] : null;
    }
}
