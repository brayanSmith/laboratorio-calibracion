<?php

namespace App\Http\Controllers;

use App\Models\ComentarioMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ComentarioMantenimientoController extends Controller
{
    /**
     * Remove the specified comentario.
     */
    public function destroy(ComentarioMantenimiento $comentarioMantenimiento): RedirectResponse
    {
        Gate::authorize('update', $comentarioMantenimiento->mantenimiento);

        $comentarioMantenimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comentario eliminado.')]);

        return back();
    }
}
