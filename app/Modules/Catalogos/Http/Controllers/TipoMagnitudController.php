<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreTipoMagnitudRequest;
use App\Modules\Catalogos\Http\Requests\UpdateTipoMagnitudRequest;
use App\Modules\Catalogos\Models\TipoMagnitud;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoMagnitudController extends Controller
{
    use EliminaPorLote;

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
     * Remove the specified tipoMagnitud.
     */
    public function destroy(TipoMagnitud $tipoMagnitud): RedirectResponse
    {
        Gate::authorize('delete', $tipoMagnitud);

        $motivo = $this->eliminarRegistro($tipoMagnitud);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($tipoMagnitud, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tipo de magnitud eliminado.')]);

        return to_route('tipos-magnitud.index');
    }

    protected function modeloPorLote(): string
    {
        return TipoMagnitud::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::TiposMagnitudEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Especificaciones técnicas de equipos'), $registro->equipoEspecificacionTecnica()->with('equipo:id,codigo'), fn (Model $especificacion): ?string => $especificacion->equipo?->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  TipoMagnitud  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipoEspecificacionTecnica()->exists()) {
            return __('No se puede eliminar un tipo de magnitud que tiene especificaciones técnicas asociadas.');
        }

        $registro->delete();

        return null;
    }
}
