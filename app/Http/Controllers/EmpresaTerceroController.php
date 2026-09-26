<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpresasTerceras\StoreEmpresaTerceroRequest;
use App\Http\Requests\EmpresasTerceras\UpdateEmpresaTerceroRequest;
use App\Models\EmpresaTercero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaTerceroController extends Controller
{
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa tercera creada.')]);

        return to_route('empresas-terceras.index');
    }

    /**
     * Update the specified empresa tercero.
     */
    public function update(UpdateEmpresaTerceroRequest $request, EmpresaTercero $empresaTercero): RedirectResponse
    {
        $empresaTercero->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa tercera actualizada.')]);

        return to_route('empresas-terceras.index');
    }

    /**
     * Remove the specified empresa tercero.
     */
    public function destroy(EmpresaTercero $empresaTercero): RedirectResponse
    {
        Gate::authorize('delete', $empresaTercero);

        if ($empresaTercero->servicioTercero()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar una empresa tercera que tiene servicios asociados.'),
            ]);

            return back();
        }

        $empresaTercero->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa tercera eliminada.')]);

        return to_route('empresas-terceras.index');
    }
}
