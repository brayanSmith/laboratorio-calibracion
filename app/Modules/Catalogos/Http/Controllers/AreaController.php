<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreAreaRequest;
use App\Modules\Catalogos\Http\Requests\UpdateAreaRequest;
use App\Modules\Catalogos\Models\Area;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    use EliminaPorLote;

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

        $motivo = $this->eliminarRegistro($area);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($area, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Área eliminada.')]);

        return to_route('areas.index');
    }

    protected function modeloPorLote(): string
    {
        return Area::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::AreasEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Equipos'), $registro->equipo(), fn (Model $equipo): string => $equipo->codigo),
            self::dependencia(__('Bahías'), $registro->bahia(), fn (Model $bahia): string => $bahia->nombre),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  Area  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipo()->exists() || $registro->bahia()->exists()) {
            return __('No se puede eliminar un área que tiene equipos o bahías asociados. Reasígnalos primero.');
        }

        $registro->delete();

        return null;
    }
}
