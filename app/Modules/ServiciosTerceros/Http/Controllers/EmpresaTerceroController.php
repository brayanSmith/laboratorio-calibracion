<?php

namespace App\Modules\ServiciosTerceros\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Modules\ServiciosTerceros\Http\Requests\StoreEmpresaTerceroRequest;
use App\Modules\ServiciosTerceros\Http\Requests\UpdateEmpresaTerceroRequest;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaTerceroController extends Controller
{
    use EliminaPorLote;

    /**
     * Display the empresas terceras of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EmpresaTercero::class);

        $empresasTerceras = EmpresaTercero::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('servicioTercero as servicios_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit', 'direccion', 'telefono', 'email'])
            ->map(fn (EmpresaTercero $empresaTercero) => [
                'id' => $empresaTercero->id,
                'nombre' => $empresaTercero->nombre,
                'nit' => $empresaTercero->nit,
                'direccion' => $empresaTercero->direccion,
                'telefono' => $empresaTercero->telefono,
                'email' => $empresaTercero->email,
                'servicios_count' => (int) $empresaTercero->getAttribute('servicios_count'),
            ]);

        return Inertia::render('empresas-terceras/index', [
            'empresasTerceras' => $empresasTerceras,
        ]);
    }

    /**
     * Store a newly created empresa tercero.
     */
    public function store(StoreEmpresaTerceroRequest $request): RedirectResponse
    {
        EmpresaTercero::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio tercero creado.')]);

        return to_route('empresas-terceras.index');
    }

    /**
     * Update the specified empresa tercero.
     */
    public function update(UpdateEmpresaTerceroRequest $request, EmpresaTercero $empresaTercero): RedirectResponse
    {
        $empresaTercero->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio tercero actualizado.')]);

        return to_route('empresas-terceras.index');
    }

    /**
     * Remove the specified empresaTercero.
     */
    public function destroy(EmpresaTercero $empresaTercero): RedirectResponse
    {
        Gate::authorize('delete', $empresaTercero);

        $motivo = $this->eliminarRegistro($empresaTercero);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($empresaTercero, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio tercero eliminado.')]);

        return to_route('empresas-terceras.index');
    }

    protected function modeloPorLote(): string
    {
        return EmpresaTercero::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::EmpresasTercerasEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Servicios de terceros'), $registro->servicioTercero()->with('ordenTrabajo:id,codigo'), fn (Model $dependiente): ?string => $dependiente->ordenTrabajo?->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  EmpresaTercero  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->servicioTercero()->exists()) {
            return __('No se puede eliminar un laboratorio tercero que tiene servicios asociados.');
        }

        $registro->delete();

        return null;
    }
}
