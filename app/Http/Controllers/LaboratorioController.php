<?php

namespace App\Http\Controllers;

use App\Http\Requests\Laboratorios\StoreLaboratorioRequest;
use App\Http\Requests\Laboratorios\UpdateLaboratorioRequest;
use App\Models\Laboratorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LaboratorioController extends Controller
{
    /**
     * Display the laboratorios of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Laboratorio::class);

        $laboratorios = Laboratorio::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('calibracion as calibraciones_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'direccion'])
            ->map(fn (Laboratorio $laboratorio) => [
                'id' => $laboratorio->id,
                'nombre' => $laboratorio->nombre,
                'descripcion' => $laboratorio->descripcion,
                'direccion' => $laboratorio->direccion,
                'calibraciones_count' => (int) $laboratorio->getAttribute('calibraciones_count'),
            ]);

        return Inertia::render('laboratorios/index', [
            'laboratorios' => $laboratorios,
        ]);
    }

    /**
     * Store a newly created laboratorio.
     */
    public function store(StoreLaboratorioRequest $request): RedirectResponse
    {
        Laboratorio::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio creado.')]);

        return to_route('laboratorios.index');
    }

    /**
     * Update the specified laboratorio.
     */
    public function update(UpdateLaboratorioRequest $request, Laboratorio $laboratorio): RedirectResponse
    {
        $laboratorio->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio actualizado.')]);

        return to_route('laboratorios.index');
    }

    /**
     * Remove the specified laboratorio.
     */
    public function destroy(Laboratorio $laboratorio): RedirectResponse
    {
        Gate::authorize('delete', $laboratorio);

        if ($laboratorio->calibracion()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un laboratorio que tiene calibraciones asociadas.'),
            ]);

            return back();
        }

        $laboratorio->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Laboratorio eliminado.')]);

        return to_route('laboratorios.index');
    }
}
