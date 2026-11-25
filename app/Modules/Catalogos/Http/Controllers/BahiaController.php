<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogos\Http\Requests\AsignarClientesBahiasRequest;
use App\Modules\Catalogos\Http\Requests\AsignarUsuariosBahiasRequest;
use App\Modules\Catalogos\Http\Requests\StoreBahiaRequest;
use App\Modules\Catalogos\Http\Requests\UpdateBahiaRequest;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BahiaController extends Controller
{
    use EliminaPorLote;

    /**
     * Display the bahias of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bahia::class);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('bahias/index', [
            'bahias' => Bahia::listadoConUsuarios($tenantId),
            'usuarios' => $request->user()->can('asignarUsuarios', Bahia::class)
                ? User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'clientes' => $request->user()->can('asignarClientes', Bahia::class)
                ? Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'numero_documento'])
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

        // Vuelve a la pantalla desde la que se asignó (Bahías o Recordatorios).
        return back(fallback: route('bahias.index'));
    }

    /**
     * Assign clientes to the bahías (varios clientes por bahía y varias bahías por cliente):
     * cada bahía enviada queda con exactamente los clientes indicados.
     */
    public function asignarClientes(AsignarClientesBahiasRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('asignaciones') as $asignacion) {
                Bahia::query()->findOrFail($asignacion['bahia_id'])->clientes()->sync($asignacion['cliente_ids']);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clientes asignados a las bahías.')]);

        return back(fallback: route('bahias.index'));
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

        $motivo = $this->eliminarRegistro($bahia);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($bahia, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bahía eliminada.')]);

        return to_route('bahias.index');
    }

    protected function modeloPorLote(): string
    {
        return Bahia::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::BahiasEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Equipos'), $registro->equipo(), fn (Model $equipo): string => $equipo->codigo),
            self::dependencia(__('Ingresos'), $registro->ingreso(), fn (Model $ingreso): string => $ingreso->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  Bahia  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipo()->exists() || $registro->ingreso()->exists()) {
            return __('No se puede eliminar una bahía que tiene equipos o ingresos asociados.');
        }

        $registro->delete();

        return null;
    }
}
