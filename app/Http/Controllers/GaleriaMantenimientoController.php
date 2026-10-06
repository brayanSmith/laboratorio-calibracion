<?php

namespace App\Http\Controllers;

use App\Models\GaleriaMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GaleriaMantenimientoController extends Controller
{
    /**
     * Remove the specified foto.
     */
    public function destroy(GaleriaMantenimiento $galeriaMantenimiento): RedirectResponse
    {
        Gate::authorize('update', $galeriaMantenimiento->mantenimiento);

        Storage::disk('public')->delete($galeriaMantenimiento->imagen);

        $galeriaMantenimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foto eliminada.')]);

        return back();
    }
}
