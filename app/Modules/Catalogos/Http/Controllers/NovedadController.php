<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreNovedadRequest;
use App\Modules\Catalogos\Http\Requests\UpdateNovedadRequest;
use App\Modules\Catalogos\Models\Novedad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NovedadController extends Controller
{
    use EliminaPorLote;

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
            ->get(['id', 'nombre', 'categoria', 'inactivar_equipo', 'es_motivo_cancelacion'])
            ->map(fn (Novedad $novedad) => [
                'id' => $novedad->id,
                'nombre' => $novedad->nombre,
                'categoria' => $novedad->categoria,
                'inactivar_equipo' => $novedad->inactivar_equipo,
                'es_motivo_cancelacion' => $novedad->es_motivo_cancelacion,
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

        $motivo = $this->eliminarRegistro($novedad);

        if ($motivo !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $motivo]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Novedad eliminada.')]);

        return to_route('novedades.index');
    }

    protected function modeloPorLote(): string
    {
        return Novedad::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::NovedadesEliminarLote;
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  Novedad  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->calibracion()->exists() || $registro->mantenimiento()->exists() || $registro->despacho()->exists() || $registro->equipoProgramacion()->exists()) {
            return __('No se puede eliminar una novedad que ya está en uso.');
        }

        $registro->delete();

        return null;
    }
}
