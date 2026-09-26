<?php

namespace App\Http\Controllers;

use App\Http\Requests\Areas\StoreAreaRequest;
use App\Http\Requests\Areas\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    /**
     * Display the areas of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Area::class);

        $areas = Area::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount(['equipo as equipos_count', 'bahia as bahias_count'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'direccion'])
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'nombre' => $area->nombre,
                'descripcion' => $area->descripcion,
                'direccion' => $area->direccion,
                'equipos_count' => (int) $area->getAttribute('equipos_count'),
                'bahias_count' => (int) $area->getAttribute('bahias_count'),
            ]);

        return Inertia::render('areas/index', [
            'areas' => $areas,
        ]);
    }

    /**
     * Store a newly created area.
     */
    public function store(StoreAreaRequest $request): RedirectResponse
    {
        Area::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Área creada.')]);

        return to_route('areas.index');
    }

    /**
     * Update the specified area.
     */
    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Área actualizada.')]);

        return to_route('areas.index');
    }

    /**
     * Remove the specified area.
     */
    public function destroy(Area $area): RedirectResponse
    {
        Gate::authorize('delete', $area);

        if ($area->equipo()->exists() || $area->bahia()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un área que tiene equipos o bahías asociados. Reasígnalos primero.'),
            ]);

            return back();
        }

        $area->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Área eliminada.')]);

        return to_route('areas.index');
    }
}
