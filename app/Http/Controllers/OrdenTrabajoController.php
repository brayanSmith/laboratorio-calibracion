<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrdenesTrabajo\StoreOrdenTrabajoRequest;
use App\Models\EquipoProgramacion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoCheckList;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrdenTrabajoController extends Controller
{
    /**
     * List equipos ya recibidos (ingresado en su ingreso) que todavía no tienen una
     * orden de trabajo, para elegir cuáles quedan listos para mantenimiento.
     */
    public function equiposListos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EquipoProgramacion::class);

        $tenantId = $request->user()->tenant_id;

        $equipos = EquipoProgramacion::query()
            ->where('tenant_id', $tenantId)
            ->where('ingresado', true)
            ->whereDoesntHave('ordenesTrabajo')
            ->whereHas('ingreso', fn ($query) => $query->where('estado_ingreso', 'RECIBIDO'))
            ->with('equipo:id,codigo,modelo,cliente_id,tipo_equipo_id', 'equipo.cliente:id,nombre', 'equipo.tipoEquipo:id,nombre')
            ->orderBy('id')
            ->get(['id', 'equipo_id']);

        return response()->json($equipos->map(fn (EquipoProgramacion $programacion) => [
            'id' => $programacion->id,
            'equipo' => [
                'id' => $programacion->equipo->id,
                'codigo' => $programacion->equipo->codigo,
                'modelo' => $programacion->equipo->modelo,
                'tipo_equipo' => ['nombre' => $programacion->equipo->tipoEquipo->nombre],
                'cliente' => ['nombre' => $programacion->equipo->cliente->nombre],
            ],
        ])->values());
    }

    /**
     * Create an orden de trabajo for every equipo marcado con listo_para_mantenimiento,
     * con un código secuencial por tenant (OT-0001, OT-0002...). Si además se asignó a
     * un tercero, crea el servicio_tercero correspondiente; si no, crea el mantenimiento
     * con el técnico elegido y su checklist, copiado del tipo de equipo.
     */
    public function store(StoreOrdenTrabajoRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $equiposListos = $request->validated('equipos_listos', []);

        $programaciones = EquipoProgramacion::query()
            ->whereIn('id', array_column($equiposListos, 'id'))
            ->with('equipo.tipoEquipo.tipoEquipoCheckList')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($equiposListos, $programaciones, $tenantId) {
            $siguienteNumero = OrdenTrabajo::withTrashed()->where('tenant_id', $tenantId)->count() + 1;

            foreach ($equiposListos as $equipo) {
                if (! $equipo['listo_para_mantenimiento']) {
                    continue;
                }

                $ordenTrabajo = OrdenTrabajo::create([
                    'codigo' => 'OT-'.str_pad((string) $siguienteNumero, 4, '0', STR_PAD_LEFT),
                    'equipo_programacion_id' => $equipo['id'],
                    'fecha_programada_orden_trabajo' => now()->toDateString(),
                    'estado' => 'EN_BAHIA',
                    'listo_para_mantenimiento' => true,
                    'mantenimiento_asignado_tercero' => $equipo['mantenimiento_asignado_tercero'],
                    'tenant_id' => $tenantId,
                ]);

                $siguienteNumero++;

                if ($equipo['mantenimiento_asignado_tercero']) {
                    ServicioTercero::create([
                        'orden_trabajo_id' => $ordenTrabajo->id,
                        'tipo_servicio' => 'MANTENIMIENTO',
                        'empresa_tercero_id' => $equipo['empresa_tercero_id'] ?? null,
                        'tenant_id' => $tenantId,
                    ]);
                } else {
                    $programacion = $programaciones[$equipo['id']];

                    $mantenimiento = Mantenimiento::create([
                        'orden_trabajo_id' => $ordenTrabajo->id,
                        'tipo_mantenimiento' => $programacion->tipo_mantenimiento,
                        'fecha_mantenimiento' => now()->toDateString(),
                        'tecnico_id' => $equipo['tecnico_id'] ?? null,
                        'novedad_id' => $equipo['novedad_id'] ?? null,
                        'tenant_id' => $tenantId,
                    ]);

                    foreach ($programacion->equipo->tipoEquipo->tipoEquipoCheckList as $checkListItem) {
                        MantenimientoCheckList::create([
                            'mantenimiento_id' => $mantenimiento->id,
                            'tipo_equipo_check_list_id' => $checkListItem->id,
                            'tenant_id' => $tenantId,
                        ]);
                    }
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento agendado.')]);

        return back();
    }
}
