<?php

namespace App\Http\Controllers;

use App\Models\ItemMantenimiento;
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
        Gate::authorize('update', $itemMantenimiento->mantenimiento);

        $itemMantenimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem eliminado.')]);

        return back();
    }
}
