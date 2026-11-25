<?php

namespace App\Modules\Calibraciones\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\Calibraciones\Http\Requests\StoreProcedimientoCalibracionRequest;
use App\Modules\Calibraciones\Http\Requests\UpdateProcedimientoCalibracionRequest;
use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProcedimientoCalibracionController extends Controller
{
    use EliminaPorLote;

    /**
     * Display the procedimientos de calibración of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProcedimientoCalibracion::class);

        $procedimientosCalibracion = ProcedimientoCalibracion::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('calibracion as calibraciones_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (ProcedimientoCalibracion $procedimientoCalibracion) => [
                'id' => $procedimientoCalibracion->id,
                'nombre' => $procedimientoCalibracion->nombre,
                'calibraciones_count' => (int) $procedimientoCalibracion->getAttribute('calibraciones_count'),
            ]);

        return Inertia::render('procedimientos-calibracion/index', [
            'procedimientosCalibracion' => $procedimientosCalibracion,
        ]);
    }

    /**
     * Store a newly created procedimiento de calibración.
     */
    public function store(StoreProcedimientoCalibracionRequest $request): RedirectResponse
    {
        ProcedimientoCalibracion::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración creado.')]);

        return to_route('procedimientos-calibracion.index');
    }

    /**
     * Update the specified procedimiento de calibración.
     */
    public function update(UpdateProcedimientoCalibracionRequest $request, ProcedimientoCalibracion $procedimientoCalibracion): RedirectResponse
    {
        $procedimientoCalibracion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración actualizado.')]);

        return to_route('procedimientos-calibracion.index');
    }

    /**
     * Remove the specified procedimientoCalibracion.
     */
    public function destroy(ProcedimientoCalibracion $procedimientoCalibracion): RedirectResponse
    {
        Gate::authorize('delete', $procedimientoCalibracion);

        $motivo = $this->eliminarRegistro($procedimientoCalibracion);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($procedimientoCalibracion, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Procedimiento de calibración eliminado.')]);

        return to_route('procedimientos-calibracion.index');
    }

    protected function modeloPorLote(): string
    {
        return ProcedimientoCalibracion::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::ProcedimientosCalibracionEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Calibraciones'), $registro->calibracion()->with('ordenTrabajo:id,codigo'), fn (Model $dependiente): ?string => $dependiente->ordenTrabajo?->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  ProcedimientoCalibracion  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->calibracion()->exists()) {
            return __('No se puede eliminar un procedimiento de calibración que tiene calibraciones asociadas.');
        }

        $registro->delete();

        return null;
    }
}
