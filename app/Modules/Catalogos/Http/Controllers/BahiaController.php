<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogos\Http\Requests\AsignarUsuariosBahiasRequest;
use App\Modules\Catalogos\Http\Requests\StoreBahiaRequest;
use App\Modules\Catalogos\Http\Requests\UpdateBahiaRequest;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BahiaController extends Controller
{
    /**
     * Display the bahias of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bahia::class);

        $tenantId = $request->user()->tenant_id;

        $bahias = Bahia::query()
            ->where('tenant_id', $tenantId)
            ->with(['area:id,nombre', 'usuarios:id'])
            ->withCount('equipo as equipos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'area_id'])
            ->map(fn (Bahia $bahia) => [
                'id' => $bahia->id,
                'nombre' => $bahia->nombre,
                'area_id' => $bahia->area_id,
                'area_nombre' => $bahia->area->nombre,
                'equipos_count' => (int) $bahia->getAttribute('equipos_count'),
                'usuario_ids' => $bahia->usuarios->pluck('id')->values(),
            ]);

        return Inertia::render('bahias/index', [
            'bahias' => $bahias,
            'usuarios' => $request->user()->can('asignarUsuarios', Bahia::class)
                ? User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'areas' => Area::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion'])
                ->map(fn (Area $area) => ['id' => $area->id, 'nombre' => $area->nombre, 'etiqueta' => $area->etiqueta]),
        ]);
    }

    /**
     * Store a newly created bahia.
     */
    public function store(StoreBahiaRequest $request): RedirectResponse
    {
        Bahia::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía creada.')]);

        return to_route('bahias.index');
    }

    /**
     * Assign usuarios to the bahías (varios usuarios por bahía y varias bahías por usuario):
     * cada bahía enviada queda con exactamente los usuarios indicados.
     */
    public function asignarUsuarios(AsignarUsuariosBahiasRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('asignaciones') as $asignacion) {
                Bahia::query()->findOrFail($asignacion['bahia_id'])->usuarios()->sync($asignacion['usuario_ids']);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuarios asignados a las bahías.')]);

        return to_route('bahias.index');
    }

    /**
     * Update the specified bahia.
     */
    public function update(UpdateBahiaRequest $request, Bahia $bahia): RedirectResponse
    {
        $bahia->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía actualizada.')]);

        return to_route('bahias.index');
    }

    /**
     * Remove the specified bahia.
     */
    public function destroy(Bahia $bahia): RedirectResponse
    {
        Gate::authorize('delete', $bahia);

        if ($bahia->equipo()->exists() || $bahia->ingreso()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar una bahía que tiene equipos o ingresos asociados.'),
            ]);

            return back();
        }

        $bahia->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía eliminada.')]);

        return to_route('bahias.index');
    }
}
