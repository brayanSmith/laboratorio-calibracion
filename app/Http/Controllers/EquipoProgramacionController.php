<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\BuscarEquipoProgramacionRequest;
use App\Http\Requests\Equipos\ListarEquiposPorBahiaRequest;
use App\Http\Requests\Equipos\StoreEquipoProgramacionRequest;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EquipoProgramacionController extends Controller
{
    /**
     * Search programaciones de servicio due within a date range, at a bahía.
     *
     * Used from the Ingreso form to preview which equipos are expected to come in
     * for mantenimiento preventivo or calibración during the ingreso's stay. Only
     * programaciones PREVENTIVO (the ones with a fecha de programación) and still
     * PENDIENTE are returned; CORRECTIVO ones are scheduled directly from an ingreso
     * instead, and ones already AGENDADO or CANCELADO are excluded.
     */
    public function buscar(BuscarEquipoProgramacionRequest $request): JsonResponse
    {
        $programaciones = EquipoProgramacion::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('tipo_mantenimiento', 'PREVENTIVO')
            ->where('estado_programacion', 'PENDIENTE')
            ->whereBetween('fecha_proximo_servicio', [
                $request->validated('desde'),
                $request->validated('hasta'),
            ])
            ->whereHas('equipo', fn ($query) => $query->where('bahia_id', $request->validated('bahia_id')))
            ->with(
                'equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'equipo.cliente:id,nombre',
                'equipo.tipoEquipo:id,nombre',
            )
            ->orderBy('fecha_proximo_servicio')
            ->get();

        return response()->json($programaciones);
    }

    /**
     * List equipos available at a bahía, to schedule a mantenimiento correctivo from an ingreso.
     */
    public function equiposDisponibles(ListarEquiposPorBahiaRequest $request): JsonResponse
    {
        $equipos = Equipo::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('bahia_id', $request->validated('bahia_id'))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'modelo']);

        return response()->json($equipos);
    }

    /**
     * Store a newly created programacion de servicio for the equipo.
     *
     * An equipo can have more than one (e.g. one for mantenimiento and another for calibración).
     */
    public function store(StoreEquipoProgramacionRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->equipoProgramaciones()->create([
            ...$request->validated(),
            'tipo_servicio' => implode(',', $request->validated('tipo_servicio')),
            'fecha_proximo_servicio' => EquipoProgramacion::calcularFechaProximoServicio(
                $request->validated('fecha_ultimo_servicio'),
                $request->validated('intervalo_servicio'),
                $request->validated('intervalo_unidad'),
            ),
            'tenant_id' => $equipo->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Programación de servicio agregada.')]);

        return back();
    }

    /**
     * Remove the specified programacion de servicio.
     */
    public function destroy(EquipoProgramacion $equipoProgramacion): RedirectResponse
    {
        Gate::authorize('delete', $equipoProgramacion);

        $equipoProgramacion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Programación de servicio eliminada.')]);

        return back();
    }
}
