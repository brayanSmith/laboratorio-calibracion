<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calibraciones\FinalizarCalibracionRequest;
use App\Http\Requests\Calibraciones\UpdateCalibracionRequest;
use App\Models\Area;
use App\Models\Calibracion;
use App\Models\Despacho;
use App\Models\DetalleMedicionCalibracion;
use App\Models\Laboratorio;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ProcedimientoCalibracion;
use App\Models\TiempoServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalibracionController extends Controller
{
    /**
     * Display the calibraciones of the tenant. No "crear": una calibración solo se
     * origina desde "Agendar Calibraciones" (ver OrdenTrabajoController::storeCalibracion()).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Calibracion::class);

        $tenantId = $request->user()->tenant_id;

        // Para saber si cada calibración ya tiene un tiempo_servicio abierto (y desde
        // cuándo), para mostrar el cronómetro en vez del botón "Iniciar".
        $tiemposActivos = TiempoServicio::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', 'CALIBRACION')
            ->where('estado_tiempo', 'INICIO')
            ->whereNull('fin')
            ->get(['orden_trabajo_id', 'inicio'])
            ->keyBy('orden_trabajo_id');

        $calibraciones = Calibracion::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id,fabricante_id,numero_serie,tipo_tecnologia,ficha_tecnica',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.fabricante:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre,tipo_mantenimiento',
                'laboratorio:id,nombre',
                'solicitante:id,nombre',
                'tecnico:id,name',
                'procedimiento:id,nombre',
                'novedad:id,nombre',
                'detalleMedicionCalibracion.unidadMedida:id,simbolo',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Calibracion $calibracion) => [
                'id' => $calibracion->id,
                'orden_trabajo_id' => $calibracion->orden_trabajo_id,
                'orden_trabajo_codigo' => $calibracion->ordenTrabajo->codigo,
                'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
                'laboratorio_id' => $calibracion->laboratorio_id,
                'laboratorio_nombre' => $calibracion->laboratorio?->nombre,
                'solicitante_id' => $calibracion->solicitante_id,
                'solicitante_nombre' => $calibracion->solicitante?->nombre,
                'tecnico_id' => $calibracion->tecnico_id,
                'tecnico_nombre' => $calibracion->tecnico->name,
                'temperatura' => $calibracion->temperatura,
                'humedad' => $calibracion->humedad,
                'procedimiento_id' => $calibracion->procedimiento_id,
                'procedimiento_nombre' => $calibracion->procedimiento?->nombre,
                'ajustes_requeridos' => $calibracion->ajustes_requeridos,
                'estado_calibracion' => $calibracion->estado_calibracion,
                'firmado' => $calibracion->firmado,
                'novedad_id' => $calibracion->novedad_id,
                'novedad_nombre' => $calibracion->novedad?->nombre,
                'tiempo_servicio_inicio' => $tiemposActivos->get($calibracion->orden_trabajo_id)?->inicio->toIso8601String(),
                'equipo' => [
                    'codigo' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->codigo,
                    'modelo' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->modelo,
                    'numero_serie' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->numero_serie,
                    'tipo_tecnologia' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->tipo_tecnologia,
                    'ficha_tecnica' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->ficha_tecnica,
                    'fabricante' => ['nombre' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->fabricante->nombre],
                    'tipo_equipo' => [
                        'nombre' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre,
                        'tipo_mantenimiento' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->tipo_mantenimiento,
                    ],
                    'cliente' => ['nombre' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
                ],
                'detalles_medicion' => $calibracion->detalleMedicionCalibracion
                    ->sortBy('id')
                    ->map(fn (DetalleMedicionCalibracion $detalle) => [
                        'id' => $detalle->id,
                        'valor_referencia' => $detalle->valor_referencia,
                        'unidad_simbolo' => $detalle->unidadMedida->simbolo,
                        'valor_instrumento' => $detalle->valor_instrumento,
                        'error_encontrado' => $detalle->error_encontrado,
                        'emp' => $detalle->emp,
                        'incertidumbre' => $detalle->incertidumbre,
                        'error_porcentaje' => $detalle->error_porcentaje,
                        'emp_porcentaje' => $detalle->emp_porcentaje,
                        'emp_porcentaje_positivo' => $detalle->emp_porcentaje_positivo,
                        'emp_porcentaje_negativo' => $detalle->emp_porcentaje_negativo,
                        'resultado_calibracion' => $detalle->resultado_calibracion,
                    ])->values(),
            ]);

        return Inertia::render('calibraciones/index', [
            'calibraciones' => $calibraciones,
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'laboratorios' => Laboratorio::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'direccion', 'descripcion']),
            'procedimientos' => ProcedimientoCalibracion::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesCalibracion' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Start the calibracion: opens a tiempo_servicio with estado_tiempo = INICIO y sin
     * fin, para medir cuánto dura. Se puede volver a iniciar más adelante (ej. tras una
     * pausa), así que no valida que no haya uno ya abierto.
     */
    public function iniciar(Calibracion $calibracion): RedirectResponse
    {
        Gate::authorize('update', $calibracion);

        TiempoServicio::create([
            'orden_trabajo_id' => $calibracion->orden_trabajo_id,
            'tipo_servicio' => 'CALIBRACION',
            'inicio' => now(),
            'estado_tiempo' => 'INICIO',
            'es_tercero' => false,
            'tenant_id' => $calibracion->tenant_id,
        ]);

        return back();
    }

    /**
     * Update the specified calibracion: sus datos y los resultados de los
     * detalle_medicion_calibracion que ya existían (ver
     * OrdenTrabajoController::crearDetallesMedicionCalibracion()). No crea ni elimina
     * detalles, solo completa los valores de la medición real.
     */
    public function update(UpdateCalibracionRequest $request, Calibracion $calibracion): RedirectResponse
    {
        DB::transaction(function () use ($request, $calibracion): void {
            $calibracion->update($request->safe()->only([
                'fecha_calibracion', 'laboratorio_id', 'solicitante_id', 'tecnico_id', 'temperatura',
                'humedad', 'procedimiento_id', 'ajustes_requeridos', 'novedad_id',
            ]));

            $detalles = DetalleMedicionCalibracion::query()
                ->where('calibracion_id', $calibracion->id)
                ->get()
                ->keyBy('id');

            foreach ($request->validated('detalles', []) as $datos) {
                $detalle = $detalles[$datos['id']];
                $valorInstrumento = $datos['valor_instrumento'] ?? null;

                // Error encontrado = valor de referencia - valor del instrumento; error %
                // = error encontrado / valor del instrumento. Ambos se calculan en el
                // servidor (no se aceptan del cliente) para que sean siempre consistentes
                // con el valor del instrumento guardado.
                $errorEncontrado = $valorInstrumento !== null
                    ? $detalle->valor_referencia - $valorInstrumento
                    : null;

                $errorPorcentaje = $errorEncontrado !== null && (float) $valorInstrumento !== 0.0
                    ? $errorEncontrado / $valorInstrumento
                    : null;

                // Resultado: APROBADO si el error encontrado no supera el EMP (el margen
                // de error permitido del equipo), NO_APROBADO si lo supera.
                $resultadoCalibracion = $errorEncontrado !== null
                    ? ($errorEncontrado <= $detalle->emp ? 'APROBADO' : 'NO_APROBADO')
                    : null;

                // emp_porcentaje, emp_porcentaje_positivo y emp_porcentaje_negativo no se
                // tocan aquí: son datos de referencia calculados una sola vez al agendar
                // (ver OrdenTrabajoController::crearDetallesMedicionCalibracion()).
                $detalle->update([
                    'valor_instrumento' => $valorInstrumento,
                    'error_encontrado' => $errorEncontrado,
                    'error_porcentaje' => $errorPorcentaje,
                    'resultado_calibracion' => $resultadoCalibracion,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración actualizada.')]);

        return to_route('calibraciones.index');
    }

    /**
     * Finish the calibracion: guarda el estado final (Finalizado o Devolver a
     * mantenimiento). Si quedó finalizada, marca la orden de trabajo como
     * calibracion_finalizado. Si se devuelve a mantenimiento, crea una nueva orden de
     * trabajo para el mismo equipo_programacion (sin agendar todavía), para que el equipo
     * vuelva a aparecer en "Agendar Mantenimiento" (ver
     * OrdenTrabajoController::equiposListos()). Si quedó finalizada, también crea el
     * despacho de la orden de trabajo (sin autorizar todavía) listo para "Agendar
     * Despachos" (ver OrdenTrabajoController::despachosListos()), donde se marca si la
     * entrega queda autorizada y quién la entrega. Cierra el tiempo_servicio que estaba
     * en curso (fin = ahora, duración calculada, estado_tiempo = FIN) sin importar el
     * estado elegido.
     */
    public function finalizar(FinalizarCalibracionRequest $request, Calibracion $calibracion): RedirectResponse
    {
        DB::transaction(function () use ($request, $calibracion): void {
            $estadoCalibracion = $request->validated('estado_calibracion');

            $calibracion->update([
                'estado_calibracion' => $estadoCalibracion,
                'firmado' => $request->validated('firmado'),
            ]);

            if ($estadoCalibracion === 'FINALIZADO') {
                $calibracion->ordenTrabajo->update(['calibracion_finalizado' => true]);

                Despacho::create([
                    'orden_trabajo_id' => $calibracion->orden_trabajo_id,
                    'tenant_id' => $calibracion->tenant_id,
                ]);
            }

            if ($estadoCalibracion === 'DEVOLVER_MANTENIMIENTO') {
                $siguienteNumero = OrdenTrabajo::withTrashed()->where('tenant_id', $calibracion->tenant_id)->count() + 1;

                OrdenTrabajo::create([
                    'codigo' => 'OT-'.str_pad((string) $siguienteNumero, 4, '0', STR_PAD_LEFT),
                    'equipo_programacion_id' => $calibracion->ordenTrabajo->equipo_programacion_id,
                    'fecha_programada_orden_trabajo' => now()->toDateString(),
                    'estado' => 'EN_BAHIA',
                    'devolucion' => true,
                    'tenant_id' => $calibracion->tenant_id,
                ]);
            }

            $tiempoServicio = TiempoServicio::query()
                ->where('orden_trabajo_id', $calibracion->orden_trabajo_id)
                ->where('tipo_servicio', 'CALIBRACION')
                ->where('estado_tiempo', 'INICIO')
                ->whereNull('fin')
                ->latest('id')
                ->first();

            if ($tiempoServicio) {
                $fin = now();
                $segundos = (int) $tiempoServicio->inicio->diffInSeconds($fin);

                $tiempoServicio->update([
                    'fin' => $fin,
                    'duracion' => sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60),
                    'estado_tiempo' => 'FIN',
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración finalizada.')]);

        return to_route('calibraciones.index');
    }

    /**
     * Remove the specified calibracion.
     */
    public function destroy(Calibracion $calibracion): RedirectResponse
    {
        Gate::authorize('delete', $calibracion);

        $calibracion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración eliminada.')]);

        return to_route('calibraciones.index');
    }
}
