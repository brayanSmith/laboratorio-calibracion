<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\StoreEquipoEspecificacionTecnicaRequest;
use App\Models\Equipo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EquipoEspecificacionTecnicaController extends Controller
{
    /**
     * Create or update the especificacion tecnica of the equipo.
     */
    public function store(StoreEquipoEspecificacionTecnicaRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->equipoEspecificacionTecnica()->updateOrCreate([], [
            ...$request->validated(),
            'tenant_id' => $equipo->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Especificación técnica guardada.')]);

        return back();
    }
}
