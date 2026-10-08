<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlcancesMedicion\StoreDetalleMedicionAlcanceRequest;
use App\Http\Requests\AlcancesMedicion\UpdateDetalleMedicionAlcanceRequest;
use App\Models\DetalleMedicionAlcance;
use App\Models\MedicionAlcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DetalleMedicionAlcanceController extends Controller
{
    /**
     * Store a newly created detalle for the alcance de medición.
     */
    public function store(StoreDetalleMedicionAlcanceRequest $request, MedicionAlcance $medicionAlcance): RedirectResponse
    {
        $medicionAlcance->detalleMedicionAlcance()->create([
            ...$request->validated(),
            'tenant_id' => $medicionAlcance->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Detalle agregado al alcance.')]);

        return back();
    }

    /**
     * Update the specified detalle.
     */
    public function update(UpdateDetalleMedicionAlcanceRequest $request, DetalleMedicionAlcance $detalleMedicionAlcance): RedirectResponse
    {
        $detalleMedicionAlcance->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Detalle del alcance actualizado.')]);

        return back();
    }

    /**
     * Remove the specified detalle.
     */
    public function destroy(DetalleMedicionAlcance $detalleMedicionAlcance): RedirectResponse
    {
        Gate::authorize('delete', $detalleMedicionAlcance);

        if ($detalleMedicionAlcance->detalleMedicionCalibracion()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un detalle que ya se usó en calibraciones.'),
            ]);

            return back();
        }

        $detalleMedicionAlcance->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Detalle eliminado del alcance.')]);

        return back();
    }
}
