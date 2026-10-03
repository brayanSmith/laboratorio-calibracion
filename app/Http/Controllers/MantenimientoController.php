<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mantenimientos\UpdateMantenimientoRequest;
use App\Models\Mantenimiento;
use App\Models\Novedad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MantenimientoController extends Controller
{
    /**
     * Display the mantenimientos of the tenant. No "crear": un mantenimiento solo se
     * origina desde "Agendar Mantenimiento" (ver OrdenTrabajoController::store()).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Mantenimiento::class);

        $tenantId = $request->user()->tenant_id;

        $mantenimientos = Mantenimiento::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
                'tecnico:id,name',
                'novedad:id,nombre',
            ])
            ->orderByDesc('fecha_mantenimiento')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Mantenimiento $mantenimiento) => [
                'id' => $mantenimiento->id,
                'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
                'orden_trabajo_codigo' => $mantenimiento->ordenTrabajo->codigo,
                'tipo_mantenimiento' => $mantenimiento->tipo_mantenimiento,
                'fecha_mantenimiento' => $mantenimiento->fecha_mantenimiento->toDateString(),
                'descripcion' => $mantenimiento->descripcion,
                'estado_inicial_equipo' => $mantenimiento->estado_inicial_equipo,
                'estado_final_equipo' => $mantenimiento->estado_final_equipo,
                'estado_mantenimiento' => $mantenimiento->estado_mantenimiento,
                'tecnico_id' => $mantenimiento->tecnico_id,
                'tecnico_nombre' => $mantenimiento->tecnico->name,
                'firmado' => $mantenimiento->firmado,
                'novedad_id' => $mantenimiento->novedad_id,
                'novedad_nombre' => $mantenimiento->novedad?->nombre,
                'equipo' => [
                    'codigo' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->codigo,
                    'modelo' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->modelo,
                    'tipo_equipo' => ['nombre' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre],
                    'cliente' => ['nombre' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
                ],
            ]);

        return Inertia::render('mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'novedadesMantenimiento' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Update the specified mantenimiento.
     */
    public function update(UpdateMantenimientoRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento actualizado.')]);

        return to_route('mantenimientos.index');
    }

    /**
     * Remove the specified mantenimiento.
     */
    public function destroy(Mantenimiento $mantenimiento): RedirectResponse
    {
        Gate::authorize('delete', $mantenimiento);

        $mantenimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento eliminado.')]);

        return to_route('mantenimientos.index');
    }
}
