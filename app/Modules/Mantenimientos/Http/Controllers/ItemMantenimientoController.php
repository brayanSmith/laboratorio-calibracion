<?php

namespace App\Modules\Mantenimientos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mantenimientos\Models\ItemMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ItemMantenimientoController extends Controller
{
    /**
     * Remove the specified item used.
     */
    public function destroy(ItemMantenimiento $itemMantenimiento): RedirectResponse
    {
        $mantenimiento = $itemMantenimiento->mantenimiento;

        Gate::authorize('update', $mantenimiento);

        $itemMantenimiento->delete();
        $mantenimiento->sincronizarEstadoRepuestos();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem eliminado.')]);

        return back();
    }
}
