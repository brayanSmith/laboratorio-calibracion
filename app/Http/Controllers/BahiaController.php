<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bahias\StoreBahiaRequest;
use App\Http\Requests\Bahias\UpdateBahiaRequest;
use App\Models\Area;
use App\Models\Bahia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BahiaController extends Controller
{
    /**
     * Display the bahias of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bahia::class);

        $tenantId = $request->user()->tenant_id;

        $bahias = Bahia::query()
            ->where('tenant_id', $tenantId)
            ->with('area:id,nombre')
            ->withCount('equipo as equipos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'area_id'])
            ->map(fn (Bahia $bahia) => [
                'id' => $bahia->id,
                'nombre' => $bahia->nombre,
                'area_id' => $bahia->area_id,
                'area_nombre' => $bahia->area->nombre,
                'equipos_count' => (int) $bahia->getAttribute('equipos_count'),
            ]);

        return Inertia::render('bahias/index', [
            'bahias' => $bahias,
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Store a newly created bahia.
     */
    public function store(StoreBahiaRequest $request): RedirectResponse
    {
        Bahia::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía creada.')]);

        return to_route('bahias.index');
    }

    /**
     * Update the specified bahia.
     */
    public function update(UpdateBahiaRequest $request, Bahia $bahia): RedirectResponse
    {
        $bahia->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía actualizada.')]);

        return to_route('bahias.index');
    }

    /**
     * Remove the specified bahia.
     */
    public function destroy(Bahia $bahia): RedirectResponse
    {
        Gate::authorize('delete', $bahia);

        if ($bahia->equipo()->exists() || $bahia->ingreso()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar una bahía que tiene equipos o ingresos asociados.'),
            ]);

            return back();
        }

        $bahia->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía eliminada.')]);

        return to_route('bahias.index');
    }
}
