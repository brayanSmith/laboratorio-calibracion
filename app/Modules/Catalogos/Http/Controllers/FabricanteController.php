<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreFabricanteRequest;
use App\Modules\Catalogos\Http\Requests\UpdateFabricanteRequest;
use App\Modules\Catalogos\Models\Fabricante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FabricanteController extends Controller
{
    use EliminaPorLote;

    /**
     * Display the fabricantes of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Fabricante::class);

        $fabricantes = Fabricante::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('equipo as equipos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Fabricante $fabricante) => [
                'id' => $fabricante->id,
                'nombre' => $fabricante->nombre,
                'equipos_count' => (int) $fabricante->getAttribute('equipos_count'),
            ]);

        return Inertia::render('fabricantes/index', [
            'fabricantes' => $fabricantes,
        ]);
    }

    /**
     * Store a newly created fabricante.
     */
    public function store(StoreFabricanteRequest $request): RedirectResponse
    {
        Fabricante::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fabricante creado.')]);

        return to_route('fabricantes.index');
    }

    /**
     * Update the specified fabricante.
     */
    public function update(UpdateFabricanteRequest $request, Fabricante $fabricante): RedirectResponse
    {
        $fabricante->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fabricante actualizado.')]);

        return to_route('fabricantes.index');
    }

    /**
     * Remove the specified fabricante.
     */
    public function destroy(Fabricante $fabricante): RedirectResponse
    {
        Gate::authorize('delete', $fabricante);

        $motivo = $this->eliminarRegistro($fabricante);

        if ($motivo !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $motivo]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fabricante eliminado.')]);

        return to_route('fabricantes.index');
    }

    protected function modeloPorLote(): string
    {
        return Fabricante::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::FabricantesEliminarLote;
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  Fabricante  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipo()->exists()) {
            return __('No se puede eliminar un fabricante que tiene equipos asociados. Reasígnalos primero.');
        }

        $registro->delete();

        return null;
    }
}
