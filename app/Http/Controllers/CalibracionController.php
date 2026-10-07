<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calibraciones\UpdateCalibracionRequest;
use App\Models\Area;
use App\Models\Calibracion;
use App\Models\Laboratorio;
use App\Models\Novedad;
use App\Models\ProcedimientoCalibracion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalibracionController extends Controller
{
    /**
     * Display the calibraciones of the tenant. No "crear": una calibración solo se
     * origina desde "Agendar Calibraciones" (ver OrdenTrabajoController::storeCalibracion()).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Calibracion::class);

        $tenantId = $request->user()->tenant_id;

        $calibraciones = Calibracion::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
                'laboratorio:id,nombre',
                'solicitante:id,nombre',
                'tecnico:id,name',
                'procedimiento:id,nombre',
                'novedad:id,nombre',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Calibracion $calibracion) => [
                'id' => $calibracion->id,
                'orden_trabajo_id' => $calibracion->orden_trabajo_id,
                'orden_trabajo_codigo' => $calibracion->ordenTrabajo->codigo,
                'laboratorio_id' => $calibracion->laboratorio_id,
                'laboratorio_nombre' => $calibracion->laboratorio?->nombre,
                'solicitante_id' => $calibracion->solicitante_id,
                'solicitante_nombre' => $calibracion->solicitante?->nombre,
                'tecnico_id' => $calibracion->tecnico_id,
                'tecnico_nombre' => $calibracion->tecnico->name,
                'temperatura' => $calibracion->temperatura,
                'humedad' => $calibracion->humedad,
                'procedimiento_id' => $calibracion->procedimiento_id,
                'procedimiento_nombre' => $calibracion->procedimiento?->nombre,
                'ajustes_requeridos' => $calibracion->ajustes_requeridos,
                'estado_calibracion' => $calibracion->estado_calibracion,
                'firmado' => $calibracion->firmado,
                'novedad_id' => $calibracion->novedad_id,
                'novedad_nombre' => $calibracion->novedad?->nombre,
                'equipo' => [
                    'codigo' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->codigo,
                    'modelo' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->modelo,
                    'tipo_equipo' => ['nombre' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre],
                    'cliente' => ['nombre' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
                ],
            ]);

        return Inertia::render('calibraciones/index', [
            'calibraciones' => $calibraciones,
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'laboratorios' => Laboratorio::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'procedimientos' => ProcedimientoCalibracion::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesCalibracion' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Update the specified calibracion.
     */
    public function update(UpdateCalibracionRequest $request, Calibracion $calibracion): RedirectResponse
    {
        $calibracion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración actualizada.')]);

        return to_route('calibraciones.index');
    }

    /**
     * Remove the specified calibracion.
     */
    public function destroy(Calibracion $calibracion): RedirectResponse
    {
        Gate::authorize('delete', $calibracion);

        $calibracion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración eliminada.')]);

        return to_route('calibraciones.index');
    }
}
