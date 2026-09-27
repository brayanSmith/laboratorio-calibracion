<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnidadesMedida\StoreUnidadMedidaRequest;
use App\Http\Requests\UnidadesMedida\UpdateUnidadMedidaRequest;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnidadMedidaController extends Controller
{
    /**
     * Display the unidades de medida of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', UnidadMedida::class);

        $unidadesMedida = UnidadMedida::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount([
                'equipoEspecificacionTecnica as especificaciones_count',
                'detalleMedicionAlcance as medicion_alcances_count',
                'detalleMedicionCalibracion as medicion_calibraciones_count',
            ])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'simbolo'])
            ->map(fn (UnidadMedida $unidadMedida) => [
                'id' => $unidadMedida->id,
                'nombre' => $unidadMedida->nombre,
                'simbolo' => $unidadMedida->simbolo,
                'usos_count' => (int) $unidadMedida->getAttribute('especificaciones_count')
                    + (int) $unidadMedida->getAttribute('medicion_alcances_count')
                    + (int) $unidadMedida->getAttribute('medicion_calibraciones_count'),
            ]);

        return Inertia::render('unidades-medida/index', [
            'unidadesMedida' => $unidadesMedida,
        ]);
    }

    /**
     * Store a newly created unidad de medida.
     */
    public function store(StoreUnidadMedidaRequest $request): RedirectResponse
    {
        UnidadMedida::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida creada.')]);

        return to_route('unidades-medida.index');
    }

    /**
     * Update the specified unidad de medida.
     */
    public function update(UpdateUnidadMedidaRequest $request, UnidadMedida $unidadMedida): RedirectResponse
    {
        $unidadMedida->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida actualizada.')]);

        return to_route('unidades-medida.index');
    }

    /**
     * Remove the specified unidad de medida.
     */
    public function destroy(UnidadMedida $unidadMedida): RedirectResponse
    {
        Gate::authorize('delete', $unidadMedida);

        $enUso = $unidadMedida->equipoEspecificacionTecnica()->exists()
            || $unidadMedida->detalleMedicionAlcance()->exists()
            || $unidadMedida->detalleMedicionCalibracion()->exists();

        if ($enUso) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar una unidad de medida que ya está en uso.'),
            ]);

            return back();
        }

        $unidadMedida->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida eliminada.')]);

        return to_route('unidades-medida.index');
    }
}
