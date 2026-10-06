<?php

namespace App\Http\Controllers;

use App\Models\MantenimientoDefectoIdentificado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MantenimientoDefectoIdentificadoController extends Controller
{
    /**
     * Remove the specified defecto.
     */
    public function destroy(MantenimientoDefectoIdentificado $defectoIdentificado): RedirectResponse
    {
        Gate::authorize('update', $defectoIdentificado->mantenimiento);

        $defectoIdentificado->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Defecto eliminado.')]);

        return back();
    }
}
