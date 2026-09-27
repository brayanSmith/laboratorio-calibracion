<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcedimientosCalibracion\StoreProcedimientoCalibracionRequest;
use App\Http\Requests\ProcedimientosCalibracion\UpdateProcedimientoCalibracionRequest;
use App\Models\ProcedimientoCalibracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProcedimientoCalibracionController extends Controller
{
    /**
     * Display the procedimientos de calibración of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProcedimientoCalibracion::class);

        $procedimientosCalibracion = ProcedimientoCalibracion::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('calibracion as calibraciones_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (ProcedimientoCalibracion $procedimientoCalibracion) => [
                'id' => $procedimientoCalibracion->id,
                'nombre' => $procedimientoCalibracion->nombre,
                'calibraciones_count' => (int) $procedimientoCalibracion->getAttribute('calibraciones_count'),
            ]);

        return Inertia::render('procedimientos-calibracion/index', [
            'procedimientosCalibracion' => $procedimientosCalibracion,
        ]);
    }

    /**
     * Store a newly created procedimiento de calibración.
     */
    public function store(StoreProcedimientoCalibracionRequest $request): RedirectResponse
    {
        ProcedimientoCalibracion::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración creado.')]);

        return to_route('procedimientos-calibracion.index');
    }

    /**
     * Update the specified procedimiento de calibración.
     */
    public function update(UpdateProcedimientoCalibracionRequest $request, ProcedimientoCalibracion $procedimientoCalibracion): RedirectResponse
    {
        $procedimientoCalibracion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración actualizado.')]);

        return to_route('procedimientos-calibracion.index');
    }

    /**
     * Remove the specified procedimiento de calibración.
     */
    public function destroy(ProcedimientoCalibracion $procedimientoCalibracion): RedirectResponse
    {
        Gate::authorize('delete', $procedimientoCalibracion);

        if ($procedimientoCalibracion->calibracion()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un procedimiento de calibración que tiene calibraciones asociadas.'),
            ]);

            return back();
        }

        $procedimientoCalibracion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración eliminado.')]);

        return to_route('procedimientos-calibracion.index');
    }
}
