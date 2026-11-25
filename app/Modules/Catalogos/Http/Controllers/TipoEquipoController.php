<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreTipoEquipoRequest;
use App\Modules\Catalogos\Http\Requests\UpdateTipoEquipoRequest;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoEquipoCheckList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoEquipoController extends Controller
{
    use EliminaPorLote;

    /**
     * Display the tipos de equipo of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TipoEquipo::class);

        $tiposEquipo = TipoEquipo::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('equipo as equipos_count')
            ->with(['tipoEquipoCheckList' => fn ($query) => $query->select(['id', 'tipo_equipo_id', 'nombre'])->orderBy('id')])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo_mantenimiento'])
            ->map(fn (TipoEquipo $tipoEquipo) => [
                'id' => $tipoEquipo->id,
                'nombre' => $tipoEquipo->nombre,
                'tipo_mantenimiento' => $tipoEquipo->tipo_mantenimiento,
                'equipos_count' => (int) $tipoEquipo->getAttribute('equipos_count'),
                'checklist' => $tipoEquipo->tipoEquipoCheckList->map(fn (TipoEquipoCheckList $item) => [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                ]),
            ]);

        return Inertia::render('tipos-equipo/index', [
            'tiposEquipo' => $tiposEquipo,
        ]);
    }

    /**
     * Store a newly created tipo de equipo.
     */
    public function store(StoreTipoEquipoRequest $request): RedirectResponse
    {
        TipoEquipo::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de equipo creado.')]);

        return to_route('tipos-equipo.index');
    }

    /**
     * Update the specified tipo de equipo.
     */
    public function update(UpdateTipoEquipoRequest $request, TipoEquipo $tipoEquipo): RedirectResponse
    {
        $tipoEquipo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de equipo actualizado.')]);

        return to_route('tipos-equipo.index');
    }

    /**
     * Remove the specified tipoEquipo.
     */
    public function destroy(TipoEquipo $tipoEquipo): RedirectResponse
    {
        Gate::authorize('delete', $tipoEquipo);

        $motivo = $this->eliminarRegistro($tipoEquipo);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($tipoEquipo, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de equipo eliminado.')]);

        return to_route('tipos-equipo.index');
    }

    protected function modeloPorLote(): string
    {
        return TipoEquipo::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::TiposEquipoEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Equipos'), $registro->equipo(), fn (Model $equipo): string => $equipo->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  TipoEquipo  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipo()->exists()) {
            return __('No se puede eliminar un tipo de equipo que tiene equipos asociados. Reasígnalos primero.');
        }

        $registro->delete();

        return null;
    }
}
