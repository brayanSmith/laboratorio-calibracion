<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrdenesTrabajo\StoreCalibracionAgendamientoRequest;
use App\Http\Requests\OrdenesTrabajo\StoreDespachoAgendamientoRequest;
use App\Http\Requests\OrdenesTrabajo\StoreOrdenTrabajoRequest;
use App\Models\Calibracion;
use App\Models\Despacho;
use App\Models\DetalleMedicionCalibracion;
use App\Models\EquipoProgramacion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoCheckList;
use App\Models\MedicionAlcance;
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
     * orden de trabajo agendada para mantenimiento, para elegir cuáles quedan listos. Un
     * equipo puede volver a aparecer aquí si su calibración se devolvió a mantenimiento
     * (ver CalibracionController::finalizar()): esa orden de trabajo nueva existe pero
     * todavía no está agendada (listo_para_mantenimiento sigue en false).
     */
    public function equiposListos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EquipoProgramacion::class);

        $tenantId = $request->user()->tenant_id;

        $equipos = EquipoProgramacion::query()
            ->listosParaMantenimiento($tenantId)
            ->with([
                'equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'equipo.cliente:id,nombre',
                'equipo.tipoEquipo:id,nombre',
                'ordenesTrabajo' => fn ($query) => $query->where('listo_para_mantenimiento', false),
            ])
            ->orderBy('id')
            ->get(['id', 'equipo_id']);

        return response()->json($equipos->map(function (EquipoProgramacion $programacion) {
            $ordenPendiente = $programacion->ordenesTrabajo->first();

            return [
                'id' => $programacion->id,
                'devolucion' => $ordenPendiente !== null && $ordenPendiente->devolucion,
                'equipo' => [
                    'id' => $programacion->equipo->id,
                    'codigo' => $programacion->equipo->codigo,
                    'modelo' => $programacion->equipo->modelo,
                    'tipo_equipo' => ['nombre' => $programacion->equipo->tipoEquipo->nombre],
                    'cliente' => ['nombre' => $programacion->equipo->cliente->nombre],
                ],
            ];
        })->values());
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

            // Un equipo puede llegar aquí con una orden de trabajo ya creada pero sin
            // agendar todavía (ver CalibracionController::finalizar(), que crea una al
            // devolver a mantenimiento): se reutiliza esa misma orden en vez de crear
            // otra, para que no se quede huérfana y el equipo no vuelva a aparecer en
            // esta lista después de agendarlo.
            $ordenesPendientes = OrdenTrabajo::query()
                ->whereIn('equipo_programacion_id', array_column($equiposListos, 'id'))
                ->where('listo_para_mantenimiento', false)
                ->get()
                ->keyBy('equipo_programacion_id');

            foreach ($equiposListos as $equipo) {
                if (! $equipo['listo_para_mantenimiento']) {
                    continue;
                }

                $ordenPendiente = $ordenesPendientes->get($equipo['id']);

                if ($ordenPendiente) {
                    $ordenPendiente->update([
                        'fecha_programada_orden_trabajo' => now()->toDateString(),
                        'estado' => 'EN_BAHIA',
                        'listo_para_mantenimiento' => true,
                        'mantenimiento_asignado_tercero' => $equipo['mantenimiento_asignado_tercero'],
                    ]);

                    $ordenTrabajo = $ordenPendiente;
                } else {
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
                }

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

    /**
     * List ordenes de trabajo con el mantenimiento finalizado que todavía no quedan
     * listas para calibración, para elegir cuáles agendar.
     */
    public function equiposListosCalibracion(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EquipoProgramacion::class);

        $tenantId = $request->user()->tenant_id;

        $ordenes = OrdenTrabajo::query()
            ->listosParaCalibracion($tenantId)
            ->with(
                'equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'equipoProgramacion.equipo.cliente:id,nombre',
                'equipoProgramacion.equipo.tipoEquipo:id,nombre',
            )
            ->orderBy('id')
            ->get(['id', 'equipo_programacion_id']);

        return response()->json($ordenes->map(fn (OrdenTrabajo $orden) => [
            'id' => $orden->id,
            'equipo' => [
                'id' => $orden->equipoProgramacion->equipo->id,
                'codigo' => $orden->equipoProgramacion->equipo->codigo,
                'modelo' => $orden->equipoProgramacion->equipo->modelo,
                'tipo_equipo' => ['nombre' => $orden->equipoProgramacion->equipo->tipoEquipo->nombre],
                'cliente' => ['nombre' => $orden->equipoProgramacion->equipo->cliente->nombre],
            ],
        ])->values());
    }

    /**
     * Mark every orden de trabajo marcada con listo_para_calibracion. Si además se
     * asignó a un tercero, crea el servicio_tercero correspondiente; si no, crea la
     * calibración con el técnico elegido (el resto de sus datos se completan cuando se
     * realice).
     */
    public function storeCalibracion(StoreCalibracionAgendamientoRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $ordenesListas = $request->validated('ordenes_listas', []);

        $ordenes = OrdenTrabajo::query()
            ->whereIn('id', array_column($ordenesListas, 'id'))
            ->with('equipoProgramacion.equipo.equipoEspecificacionTecnica')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($ordenesListas, $ordenes, $tenantId) {
            foreach ($ordenesListas as $datos) {
                if (! $datos['listo_para_calibracion']) {
                    continue;
                }

                $ordenTrabajo = $ordenes[$datos['id']];

                $ordenTrabajo->update([
                    'listo_para_calibracion' => true,
                    'calibracion_asignado_tercero' => $datos['calibracion_asignado_tercero'],
                ]);

                if ($datos['calibracion_asignado_tercero']) {
                    ServicioTercero::create([
                        'orden_trabajo_id' => $ordenTrabajo->id,
                        'tipo_servicio' => 'CALIBRACION',
                        'empresa_tercero_id' => $datos['empresa_tercero_id'] ?? null,
                        'tenant_id' => $tenantId,
                    ]);
                } else {
                    $calibracion = Calibracion::create([
                        'orden_trabajo_id' => $ordenTrabajo->id,
                        'fecha_calibracion' => now()->toDateString(),
                        'tecnico_id' => $datos['tecnico_id'] ?? null,
                        'novedad_id' => $datos['novedad_id'] ?? null,
                        'tenant_id' => $tenantId,
                    ]);

                    $this->crearDetallesMedicionCalibracion($calibracion, $ordenTrabajo, $tenantId);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración agendada.')]);

        return back();
    }

    /**
     * List despachos creados (al finalizar una calibración, ver
     * CalibracionController::finalizar()) que todavía no tienen técnico de entrega
     * asignado, para elegir quién los entrega.
     */
    public function despachosListos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EquipoProgramacion::class);

        $tenantId = $request->user()->tenant_id;

        $despachos = Despacho::query()
            ->listosParaAgendar($tenantId)
            ->with([
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
            ])
            ->orderBy('id')
            ->get(['id', 'orden_trabajo_id', 'entrega_autorizada']);

        return response()->json($despachos->map(fn (Despacho $despacho) => [
            'id' => $despacho->id,
            'entrega_autorizada' => $despacho->entrega_autorizada,
            'equipo' => [
                'id' => $despacho->ordenTrabajo->equipoProgramacion->equipo->id,
                'codigo' => $despacho->ordenTrabajo->equipoProgramacion->equipo->codigo,
                'modelo' => $despacho->ordenTrabajo->equipoProgramacion->equipo->modelo,
                'tipo_equipo' => ['nombre' => $despacho->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre],
                'cliente' => ['nombre' => $despacho->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
            ],
        ])->values());
    }

    /**
     * Assign the técnico de entrega (y si quedó autorizada la entrega) to every despacho
     * con técnico elegido. Uno sin técnico se deja sin tocar, para revisarlo después.
     */
    public function storeDespacho(StoreDespachoAgendamientoRequest $request): RedirectResponse
    {
        $despachosListos = $request->validated('despachos_listos', []);

        $despachos = Despacho::query()
            ->whereIn('id', array_column($despachosListos, 'id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($despachosListos, $despachos) {
            foreach ($despachosListos as $datos) {
                if (empty($datos['tecnico_id'])) {
                    continue;
                }

                $despachos[$datos['id']]->update([
                    'tecnico_entrega_id' => $datos['tecnico_id'],
                    'entrega_autorizada' => $datos['entrega_autorizada'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despacho agendado.')]);

        return back();
    }

    /**
     * Crea los detalle_medicion_calibracion de una calibración recién agendada, uno por
     * cada detalle_medicion_alcance del equipo (según su tipo_equipo y el alcance_indicacion
     * de su equipo_especificacion_tecnica). Copia los datos de referencia conocidos de
     * antemano (valor_referencia, unidad_medida_id, emp, incertidumbre) y calcula emp_porcentaje
     * y emp_porcentaje_negativo a partir de ellos (ver fórmulas abajo); el resto se completa
     * cuando el técnico realiza la medición real. Si el equipo no tiene especificación
     * técnica o no hay un medicion_alcance que coincida, no crea nada.
     */
    private function crearDetallesMedicionCalibracion(Calibracion $calibracion, OrdenTrabajo $ordenTrabajo, int $tenantId): void
    {
        $equipo = $ordenTrabajo->equipoProgramacion->equipo;
        $especificacionTecnica = $equipo->equipoEspecificacionTecnica;

        if (! $especificacionTecnica) {
            return;
        }

        $medicionAlcance = MedicionAlcance::query()
            ->where('tipo_equipo_id', $equipo->tipo_equipo_id)
            ->where('alcance_indicacion', $especificacionTecnica->alcance_indicacion)
            ->with('detalleMedicionAlcance')
            ->first();

        if (! $medicionAlcance) {
            return;
        }

        foreach ($medicionAlcance->detalleMedicionAlcance as $detalleAlcance) {
            $valorReferencia = $detalleAlcance->valor_instrumento;
            $emp = $detalleAlcance->emp;

            // EMP % = EMP / valor de referencia; EMP% negativo = EMP% - (EMP% * 2), su
            // opuesto; EMP% positivo es a su vez el opuesto del negativo (es decir, el
            // mismo EMP%). Los tres son datos de referencia: no dependen de la medición
            // real, así que se calculan una sola vez aquí.
            $empPorcentaje = (float) $valorReferencia !== 0.0 ? (float) $emp / (float) $valorReferencia : null;
            $empPorcentajeNegativo = $empPorcentaje !== null ? $empPorcentaje - ($empPorcentaje * 2) : null;
            $empPorcentajePositivo = $empPorcentajeNegativo !== null ? -$empPorcentajeNegativo : null;

            DetalleMedicionCalibracion::create([
                'calibracion_id' => $calibracion->id,
                'detalle_medicion_alcance_id' => $detalleAlcance->id,
                'valor_referencia' => $valorReferencia,
                'unidad_medida_id' => $detalleAlcance->unidad_medida_id,
                'emp' => $emp,
                'incertidumbre' => $detalleAlcance->incertidumbre,
                'emp_porcentaje' => $empPorcentaje,
                'emp_porcentaje_positivo' => $empPorcentajePositivo,
                'emp_porcentaje_negativo' => $empPorcentajeNegativo,
                'tenant_id' => $tenantId,
            ]);
        }
    }
}
