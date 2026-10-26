<?php

namespace App\Modules\Calibraciones\Http\Controllers;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Modules\Calibraciones\Actions\GenerarCertificadoCalibracion;
use App\Modules\Calibraciones\Http\Requests\FinalizarCalibracionRequest;
use App\Modules\Calibraciones\Http\Requests\UpdateCalibracionRequest;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Calibraciones\Models\DetalleMedicionCalibracion;
use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\OrdenesTrabajo\Http\Controllers\OrdenTrabajoController;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Http\Controllers\ServicioTerceroController;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        $calibraciones = self::listar($request->user());

        return Inertia::render('calibraciones/index', [
            'calibraciones' => $calibraciones,
            'serviciosTerceros' => $request->user()->can(TenantPermission::CalibracionesTerceros->value)
                ? ServicioTerceroController::listar($tenantId, 'CALIBRACION')
                : [],
            'empresasTerceras' => EmpresaTercero::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'laboratorioPropio' => Empresa::laboratorioDelTenant($tenantId),
            'procedimientos' => ProcedimientoCalibracion::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesCalibracion' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Calibraciones del tenant que el usuario puede ver, listas para la tabla y las modales
     * (ver, editar, gestionar); opcionalmente solo las de un equipo (ver EquipoController::historial()).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function listar(User $user, ?int $equipoId = null): Collection
    {
        $tenantId = $user->tenant_id;

        // Para saber si cada calibración ya tiene un tiempo_servicio abierto (y desde
        // cuándo), para mostrar el cronómetro en vez del botón "Iniciar".
        $tiemposActivos = TiempoServicio::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', 'CALIBRACION')
            ->where('estado_tiempo', 'INICIO')
            ->whereNull('fin')
            ->get(['orden_trabajo_id', 'inicio'])
            ->keyBy('orden_trabajo_id');

        return Calibracion::query()
            ->where('tenant_id', $tenantId)
            ->when($equipoId !== null, fn (Builder $query) => $query->whereHas(
                'ordenTrabajo.equipoProgramacion',
                fn (Builder $programacion) => $programacion->where('equipo_id', $equipoId),
            ))
            ->visiblesPara($user)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id,fabricante_id,area_id,numero_serie,tipo_tecnologia,ficha_tecnica',
                'ordenTrabajo.equipoProgramacion.equipo.area:id,nombre,descripcion,direccion',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.fabricante:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre,tipo_mantenimiento',
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
                'laboratorio_nombre' => $calibracion->lugar_calibracion,
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
                'resultado_calibracion' => $calibracion->resultado_calibracion,
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
                    'area' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->area === null ? null : [
                        'etiqueta' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->area->etiqueta,
                        'direccion' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->area->direccion,
                        'descripcion' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->area->descripcion,
                    ],
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
    }

    /**
     * Start the calibracion: opens a tiempo_servicio with estado_tiempo = INICIO y sin
     * fin, para medir cuánto dura. Se puede volver a iniciar más adelante (ej. tras una
     * pausa), así que no valida que no haya uno ya abierto. Aceptar el inicio significa
     * que la calibración se acepta, así que pasa a EN_PROCESO.
     */
    public function iniciar(Calibracion $calibracion): RedirectResponse
    {
        Gate::authorize('iniciar', $calibracion);

        TiempoServicio::create([
            'orden_trabajo_id' => $calibracion->orden_trabajo_id,
            'tipo_servicio' => 'CALIBRACION',
            'inicio' => now(),
            'estado_tiempo' => 'INICIO',
            'es_tercero' => false,
            'tenant_id' => $calibracion->tenant_id,
        ]);

        $calibracion->update(['estado_calibracion' => 'EN_PROCESO']);

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
        DB::transaction(fn () => $this->guardarFormulario($request, $calibracion));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración actualizada.')]);

        return to_route('calibraciones.index');
    }

    /**
     * Guarda el formulario de edición de la calibración (condiciones ambientales, método,
     * novedad y los valores del instrumento con sus cálculos). Lo usan "Guardar cambios" y
     * "Finalizar", que envía el mismo formulario en una sola petición.
     */
    private function guardarFormulario(FormRequest $request, Calibracion $calibracion): void
    {
        // La fecha y el técnico no se editan aquí. El solicitante es el área del equipo y el
        // lugar es el laboratorio propio de la Empresa: se asignan en el servidor, no los envía el cliente.
        $calibracion->update([
            ...$request->safe()->only(['temperatura', 'humedad', 'procedimiento_id', 'ajustes_requeridos', 'novedad_id']),
            'solicitante_id' => $calibracion->ordenTrabajo->equipoProgramacion->equipo->area_id ?? $calibracion->solicitante_id,
            'lugar_calibracion' => Empresa::laboratorioDelTenant($calibracion->tenant_id) ?? $calibracion->lugar_calibracion,
        ]);

        $detalles = DetalleMedicionCalibracion::query()
            ->where('calibracion_id', $calibracion->id)
            ->get()
            ->keyBy('id');

        foreach ($request->validated('detalles', []) as $datos) {
            $detalle = $detalles[$datos['id']];
            $valorInstrumento = $datos['valor_instrumento'] ?? null;

            // Error encontrado = |valor de referencia - valor del instrumento| (siempre
            // positivo: importa cuánto se aleja, no hacia dónde); error % = error
            // encontrado / valor del instrumento. Ambos se calculan en el servidor (no se
            // aceptan del cliente) para que sean siempre consistentes con el valor del
            // instrumento guardado.
            $errorEncontrado = $valorInstrumento !== null
                ? abs($detalle->valor_referencia - $valorInstrumento)
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
    }

    /**
     * Finish the calibracion: guarda el estado final (Finalizado, Devolver a mantenimiento
     * o Fuera de servicio; este último deja el equipo inactivo, ver
     * Calibracion::inactivarEquipoSiCorresponde(), y no crea despacho). Si quedó finalizada, marca la orden de trabajo como
     * calibracion_finalizado. Si se devuelve a mantenimiento, crea una nueva orden de
     * trabajo para el mismo equipo_programacion (sin agendar todavía), para que el equipo
     * vuelva a aparecer en "Agendar Mantenimiento" (ver
     * OrdenTrabajoController::equiposListos()). Si quedó finalizada, también crea el
     * despacho de la orden de trabajo (sin autorizar todavía) listo para "Agendar
     * Despachos" (ver OrdenTrabajoController::despachosListos()), donde se marca si la
     * entrega queda autorizada y quién la entrega. Cierra el tiempo_servicio que estaba
     * en curso (fin = ahora, duración calculada, estado_tiempo = FIN) sin importar el
     * estado elegido. Si quedó finalizada, después genera y guarda el certificado PDF; si
     * eso falla, la calibración igual queda finalizada y el certificado se genera la
     * primera vez que se pida (ver certificado()).
     */
    public function finalizar(FinalizarCalibracionRequest $request, Calibracion $calibracion, GenerarCertificadoCalibracion $generarCertificado): RedirectResponse
    {
        DB::transaction(function () use ($request, $calibracion): void {
            // Si "Finalizar" trae el formulario de edición, se guarda en esta misma petición
            // antes de calcular el resultado, para no obligar a guardar por separado.
            if ($request->guardaFormulario()) {
                $this->guardarFormulario($request, $calibracion);
            }

            $estadoCalibracion = $request->validated('estado_calibracion');

            $calibracion->update([
                'estado_calibracion' => $estadoCalibracion,
                // Aprobado solo si ningún resultado de la medición quedó NO_APROBADO.
                'resultado_calibracion' => $estadoCalibracion === 'FINALIZADO'
                    ? ! $calibracion->detalleMedicionCalibracion()->where('resultado_calibracion', 'NO_APROBADO')->exists()
                    : null,
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

        if ($calibracion->estado_calibracion === 'FINALIZADO') {
            try {
                $generarCertificado->handle($calibracion);
            } catch (Throwable $e) {
                report($e);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calibración finalizada.')]);

        return to_route('calibraciones.index');
    }

    /**
     * Show the PDF certificate of a finished calibracion in the browser, ready to print or
     * save. It is generated when the calibracion is finished; if that copy is missing it is
     * generated again here.
     */
    public function certificado(Calibracion $calibracion, GenerarCertificadoCalibracion $generarCertificado): StreamedResponse
    {
        abort_unless(
            $calibracion->tenant_id === auth()->user()->tenant_id
                && $calibracion->estado_calibracion === 'FINALIZADO',
            404,
        );

        Gate::authorize('verPdf', $calibracion);

        $disco = Storage::disk('public');

        $ruta = $calibracion->pdf_certificado && $disco->exists($calibracion->pdf_certificado)
            ? $calibracion->pdf_certificado
            : $generarCertificado->handle($calibracion);

        return $disco->response($ruta, 'certificado-calibracion-'.$calibracion->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Vuelve a generar el certificado PDF de una calibración finalizada con los datos y el
     * diseño actuales, reemplazando el archivo guardado.
     */
    public function actualizarCertificado(Calibracion $calibracion, GenerarCertificadoCalibracion $generarCertificado): RedirectResponse
    {
        abort_unless($calibracion->estado_calibracion === 'FINALIZADO', 404);

        Gate::authorize('verPdf', $calibracion);

        $generarCertificado->handle($calibracion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificado actualizado.')]);

        return back();
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
