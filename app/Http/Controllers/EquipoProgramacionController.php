<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\StoreEquipoProgramacionRequest;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EquipoProgramacionController extends Controller
{
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
