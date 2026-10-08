<?php

namespace App\Http\Controllers;

use App\Http\Requests\Novedades\StoreNovedadRequest;
use App\Http\Requests\Novedades\UpdateNovedadRequest;
use App\Models\Novedad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NovedadController extends Controller
{
    /**
     * Display the novedades of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Novedad::class);

        $novedades = Novedad::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount([
                'calibracion as calibraciones_count',
                'mantenimiento as mantenimientos_count',
                'despacho as despachos_count',
                'equipoProgramacion as programaciones_count',
            ])
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria'])
            ->map(fn (Novedad $novedad) => [
                'id' => $novedad->id,
                'nombre' => $novedad->nombre,
                'categoria' => $novedad->categoria,
                'usos_count' => (int) $novedad->getAttribute('calibraciones_count')
                    + (int) $novedad->getAttribute('mantenimientos_count')
                    + (int) $novedad->getAttribute('despachos_count')
                    + (int) $novedad->getAttribute('programaciones_count'),
            ]);

        return Inertia::render('novedades/index', [
            'novedades' => $novedades,
        ]);
    }

    /**
     * Store a newly created novedad.
     */
    public function store(StoreNovedadRequest $request): RedirectResponse
    {
        Novedad::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Novedad creada.')]);

        return to_route('novedades.index');
    }

    /**
     * Update the specified novedad.
     */
    public function update(UpdateNovedadRequest $request, Novedad $novedad): RedirectResponse
    {
        $novedad->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Novedad actualizada.')]);

        return to_route('novedades.index');
    }

    /**
     * Remove the specified novedad.
     */
    public function destroy(Novedad $novedad): RedirectResponse
    {
        Gate::authorize('delete', $novedad);

        $enUso = $novedad->calibracion()->exists()
            || $novedad->mantenimiento()->exists()
            || $novedad->despacho()->exists()
            || $novedad->equipoProgramacion()->exists();

        if ($enUso) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar una novedad que ya está en uso.'),
            ]);

            return back();
        }

        $novedad->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Novedad eliminada.')]);

        return to_route('novedades.index');
    }
}
