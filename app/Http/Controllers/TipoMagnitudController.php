<?php

namespace App\Http\Controllers;

use App\Http\Requests\TiposMagnitud\StoreTipoMagnitudRequest;
use App\Http\Requests\TiposMagnitud\UpdateTipoMagnitudRequest;
use App\Models\TipoMagnitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoMagnitudController extends Controller
{
    /**
     * Display the tipos de magnitud of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TipoMagnitud::class);

        $tiposMagnitud = TipoMagnitud::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('equipoEspecificacionTecnica as especificaciones_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (TipoMagnitud $tipoMagnitud) => [
                'id' => $tipoMagnitud->id,
                'nombre' => $tipoMagnitud->nombre,
                'especificaciones_count' => (int) $tipoMagnitud->getAttribute('especificaciones_count'),
            ]);

        return Inertia::render('tipos-magnitud/index', [
            'tiposMagnitud' => $tiposMagnitud,
        ]);
    }

    /**
     * Store a newly created tipo de magnitud.
     */
    public function store(StoreTipoMagnitudRequest $request): RedirectResponse
    {
        TipoMagnitud::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de magnitud creado.')]);

        return to_route('tipos-magnitud.index');
    }

    /**
     * Update the specified tipo de magnitud.
     */
    public function update(UpdateTipoMagnitudRequest $request, TipoMagnitud $tipoMagnitud): RedirectResponse
    {
        $tipoMagnitud->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de magnitud actualizado.')]);

        return to_route('tipos-magnitud.index');
    }

    /**
     * Remove the specified tipo de magnitud.
     */
    public function destroy(TipoMagnitud $tipoMagnitud): RedirectResponse
    {
        Gate::authorize('delete', $tipoMagnitud);

        if ($tipoMagnitud->equipoEspecificacionTecnica()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un tipo de magnitud que tiene especificaciones técnicas asociadas.'),
            ]);

            return back();
        }

        $tipoMagnitud->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de magnitud eliminado.')]);

        return to_route('tipos-magnitud.index');
    }
}
