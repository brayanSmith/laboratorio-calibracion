<?php

namespace App\Http\Controllers;

use App\Actions\Mantenimientos\GenerarInformeMantenimiento;
use App\Http\Requests\Mantenimientos\FinalizarMantenimientoRequest;
use App\Http\Requests\Mantenimientos\UpdateMantenimientoRequest;
use App\Models\ComentarioMantenimiento;
use App\Models\EmpresaTercero;
use App\Models\GaleriaMantenimiento;
use App\Models\Item;
use App\Models\ItemMantenimiento;
use App\Models\Mantenimiento;
use App\Models\MantenimientoCheckList;
use App\Models\MantenimientoDefectoIdentificado;
use App\Models\Novedad;
use App\Models\TiempoServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MantenimientoController extends Controller
{
    /**
     * Display the mantenimientos of the tenant. No "crear": un mantenimiento solo se
     * origina desde "Agendar Mantenimiento" (ver OrdenTrabajoController::store()).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Mantenimiento::class);

        $tenantId = $request->user()->tenant_id;

        // Para saber si cada mantenimiento ya tiene un tiempo_servicio abierto (y desde
        // cuándo), para que el botón diga "Continuar" en vez de "Iniciar" y el cronómetro
        // arranque desde la hora real, no desde que se abre la modal.
        $tiemposActivos = TiempoServicio::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', 'MANTENIMIENTO')
            ->where('estado_tiempo', 'INICIO')
            ->whereNull('fin')
            ->get(['orden_trabajo_id', 'inicio'])
            ->keyBy('orden_trabajo_id');

        // Duración total (suma de los tiempos ya cerrados) de cada orden de trabajo.
        $duraciones = TiempoServicio::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', 'MANTENIMIENTO')
            ->where('es_tercero', false)
            ->where('estado_tiempo', 'FIN')
            ->get(['orden_trabajo_id', 'duracion'])
            ->groupBy('orden_trabajo_id')
            ->map(fn ($tiempos) => TiempoServicio::formatearDuracion(TiempoServicio::sumarDuraciones($tiempos->pluck('duracion'))));

        $mantenimientos = Mantenimiento::query()
            ->where('tenant_id', $tenantId)
            ->visiblesPara($request->user())
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id,fabricante_id,numero_serie,tipo_tecnologia,ficha_tecnica',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.fabricante:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre,tipo_mantenimiento',
                'tecnico:id,name',
                'novedad:id,nombre',
                'mantenimientoCheckLists.tipoEquipoCheckList:id,nombre',
                'defectoIdentificados',
                'itemMantenimientos.item:id,codigo,nombre',
                'comentarioMantenimientos.user:id,name',
                'galeriaMantenimientos',
            ])
            ->orderByDesc('fecha_mantenimiento')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Mantenimiento $mantenimiento) => [
                'id' => $mantenimiento->id,
                'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
                'orden_trabajo_codigo' => $mantenimiento->ordenTrabajo->codigo,
                'tipo_mantenimiento' => $mantenimiento->tipo_mantenimiento,
                'fecha_mantenimiento' => $mantenimiento->fecha_mantenimiento->toDateString(),
                'descripcion' => $mantenimiento->descripcion,
                'estado_inicial_equipo' => $mantenimiento->estado_inicial_equipo,
                'estado_final_equipo' => $mantenimiento->estado_final_equipo,
                'estado_mantenimiento' => $mantenimiento->estado_mantenimiento,
                'tecnico_id' => $mantenimiento->tecnico_id,
                'tecnico_nombre' => $mantenimiento->tecnico->name,
                'firmado' => $mantenimiento->firmado,
                'novedad_id' => $mantenimiento->novedad_id,
                'novedad_nombre' => $mantenimiento->novedad?->nombre,
                'tiempo_servicio_inicio' => $tiemposActivos->get($mantenimiento->orden_trabajo_id)?->inicio->toIso8601String(),
                'duracion' => $duraciones->get($mantenimiento->orden_trabajo_id),
                'equipo' => [
                    'codigo' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->codigo,
                    'modelo' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->modelo,
                    'numero_serie' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->numero_serie,
                    'tipo_tecnologia' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipo_tecnologia,
                    'ficha_tecnica' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->ficha_tecnica,
                    'fabricante' => ['nombre' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->fabricante->nombre],
                    'tipo_equipo' => [
                        'nombre' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre,
                        'tipo_mantenimiento' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->tipo_mantenimiento,
                    ],
                    'cliente' => ['nombre' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
                ],
                'checklist' => $mantenimiento->mantenimientoCheckLists->map(fn (MantenimientoCheckList $item) => [
                    'id' => $item->id,
                    'nombre' => $item->tipoEquipoCheckList->nombre,
                    'cumple' => $item->cumple,
                    'observacion' => $item->observacion,
                ])->values(),
                'defectos' => $mantenimiento->defectoIdentificados->map(fn (MantenimientoDefectoIdentificado $defecto) => [
                    'id' => $defecto->id,
                    'defecto_identificado' => $defecto->defecto_identificado,
                    'nivel_riesgo' => $defecto->nivel_riesgo,
                    'accion_correctiva' => $defecto->accion_correctiva,
                ])->values(),
                'items_usados' => $mantenimiento->itemMantenimientos->map(fn (ItemMantenimiento $itemUsado) => [
                    'id' => $itemUsado->id,
                    'item_codigo' => $itemUsado->item->codigo,
                    'item_nombre' => $itemUsado->item->nombre,
                    'cantidad' => $itemUsado->cantidad,
                    'con_stock' => $itemUsado->con_stock,
                    'precio_unitario' => (float) $itemUsado->precio_unitario,
                    'subtotal' => (float) $itemUsado->subtotal,
                ])->values(),
                'comentarios' => $mantenimiento->comentarioMantenimientos
                    ->sortByDesc('created_at')
                    ->map(fn (ComentarioMantenimiento $comentario) => [
                        'id' => $comentario->id,
                        'comentario' => $comentario->comentario,
                        'autor' => $comentario->user->name,
                        'fecha' => $comentario->created_at->toIso8601String(),
                    ])->values(),
                'galeria' => $mantenimiento->galeriaMantenimientos->map(fn (GaleriaMantenimiento $foto) => [
                    'id' => $foto->id,
                    'imagen_url' => $foto->imagenUrl(),
                    'descripcion' => $foto->descripcion,
                ])->values(),
            ]);

        return Inertia::render('mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'serviciosTerceros' => ServicioTerceroController::listar($tenantId, 'MANTENIMIENTO'),
            'empresasTerceras' => EmpresaTercero::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'novedadesMantenimiento' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->orderBy('nombre')->get(['id', 'nombre']),
            'items' => Item::query()
                ->where('tenant_id', $tenantId)
                ->conMovimientos()
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Item $item) => [
                    'id' => $item->id,
                    'codigo' => $item->codigo,
                    'nombre' => $item->nombre,
                    'stock' => $item->stock,
                    'precio_unitario' => (float) $item->precio_unitario,
                ]),
        ]);
    }

    /**
     * Start the mantenimiento: opens a tiempo_servicio with estado_tiempo = INICIO y sin
     * fin, para medir cuánto dura. Se puede volver a iniciar más adelante (ej. tras una
     * pausa), así que no valida que no haya uno ya abierto. Aceptar el inicio significa
     * que el mantenimiento se acepta, así que pasa a EN_PROCESO.
     */
    public function iniciar(Mantenimiento $mantenimiento): RedirectResponse
    {
        Gate::authorize('iniciar', $mantenimiento);

        TiempoServicio::create([
            'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
            'tipo_servicio' => 'MANTENIMIENTO',
            'inicio' => now(),
            'estado_tiempo' => 'INICIO',
            'es_tercero' => false,
            'tenant_id' => $mantenimiento->tenant_id,
        ]);

        $mantenimiento->update(['estado_mantenimiento' => 'EN_PROCESO']);

        return back();
    }

    /**
     * Revisa los ítems registrados sin stock: los que ya tienen stock suficiente pasan a
     * contar como usados (descuentan del inventario). Si ya no queda ninguno sin stock,
     * el mantenimiento deja de estar en FALTA_REPUESTOS.
     */
    public function actualizarStock(Mantenimiento $mantenimiento): RedirectResponse
    {
        Gate::authorize('update', $mantenimiento);

        $surtidos = 0;

        DB::transaction(function () use ($mantenimiento, &$surtidos): void {
            $sinStock = $mantenimiento->itemMantenimientos()->where('con_stock', false)->orderBy('id')->get();

            foreach ($sinStock as $itemUsado) {
                $stock = Item::query()->conMovimientos()->find($itemUsado->item_id)?->stock ?? 0.0;

                if ($stock >= (float) $itemUsado->cantidad) {
                    $itemUsado->update(['con_stock' => true]);
                    $surtidos++;
                }
            }

            $mantenimiento->sincronizarEstadoRepuestos();
        });

        if ($surtidos > 0) {
            Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count ítem ya tiene stock y quedó registrado como usado.|:count ítems ya tienen stock y quedaron registrados como usados.', $surtidos)]);
        }

        return back();
    }

    /**
     * Finish the mantenimiento: guarda el estado final del equipo y si quedó firmado,
     * marca el mantenimiento como FINALIZADO (y la orden de trabajo como
     * mantenimiento_finalizado), y cierra el tiempo_servicio que estaba en curso (fin =
     * ahora, duración calculada, estado_tiempo = FIN). Después genera y guarda el informe
     * PDF; si eso falla, el mantenimiento igual queda finalizado y el informe se genera
     * la primera vez que se pida (ver informe()).
     */
    public function finalizar(FinalizarMantenimientoRequest $request, Mantenimiento $mantenimiento, GenerarInformeMantenimiento $generarInforme): RedirectResponse
    {
        DB::transaction(function () use ($request, $mantenimiento): void {
            $mantenimiento->update([
                'estado_final_equipo' => $request->validated('estado_final_equipo'),
                'firmado' => $request->validated('firmado'),
                'estado_mantenimiento' => 'FINALIZADO',
            ]);

            $mantenimiento->ordenTrabajo->update(['mantenimiento_finalizado' => true]);

            $tiempoServicio = TiempoServicio::query()
                ->where('orden_trabajo_id', $mantenimiento->orden_trabajo_id)
                ->where('tipo_servicio', 'MANTENIMIENTO')
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

        try {
            $generarInforme->handle($mantenimiento);
        } catch (Throwable $e) {
            report($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento finalizado.')]);

        return to_route('mantenimientos.index');
    }

    /**
     * Show the PDF report of a finished mantenimiento in the browser, ready to print or
     * save. It is generated when the mantenimiento is finished; if that copy is missing
     * it is generated again here.
     */
    public function informe(Mantenimiento $mantenimiento, GenerarInformeMantenimiento $generarInforme): StreamedResponse
    {
        abort_unless(
            $mantenimiento->tenant_id === auth()->user()->tenant_id
                && $mantenimiento->estado_mantenimiento === 'FINALIZADO',
            404,
        );

        Gate::authorize('verPdf', $mantenimiento);

        $disco = Storage::disk('public');

        $ruta = $mantenimiento->pdf_informe && $disco->exists($mantenimiento->pdf_informe)
            ? $mantenimiento->pdf_informe
            : $generarInforme->handle($mantenimiento);

        return $disco->response($ruta, 'informe-mantenimiento-'.$mantenimiento->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Vuelve a generar el informe PDF de un mantenimiento finalizado con los datos y el
     * diseño actuales, reemplazando el archivo guardado.
     */
    public function actualizarInforme(Mantenimiento $mantenimiento, GenerarInformeMantenimiento $generarInforme): RedirectResponse
    {
        abort_unless($mantenimiento->estado_mantenimiento === 'FINALIZADO', 404);

        Gate::authorize('verPdf', $mantenimiento);

        $generarInforme->handle($mantenimiento);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Informe actualizado.')]);

        return back();
    }

    /**
     * Update the specified mantenimiento: sus datos, el checklist completo y, si se
     * llenaron, nuevos defectos identificados, ítems usados, comentarios y/o fotos (se
     * puede agregar más de uno de cada uno). Todo se guarda con una sola solicitud para
     * que la modal de gestión tenga un único botón. Una fila nueva vacía no crea nada.
     */
    public function update(UpdateMantenimientoRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        DB::transaction(function () use ($request, $mantenimiento): void {
            $mantenimiento->update($request->safe()->only([
                'fecha_mantenimiento', 'descripcion', 'estado_inicial_equipo', 'estado_final_equipo',
                'estado_mantenimiento', 'tecnico_id', 'firmado', 'novedad_id',
            ]));

            $checklistItems = MantenimientoCheckList::query()
                ->where('mantenimiento_id', $mantenimiento->id)
                ->get()
                ->keyBy('id');

            foreach ($request->validated('checklist', []) as $item) {
                $checklistItems[$item['id']]->update([
                    'cumple' => $item['cumple'],
                    'observacion' => $item['observacion'],
                ]);
            }

            foreach ($request->validated('nuevos_defectos', []) as $defecto) {
                if (filled($defecto['defecto_identificado'] ?? null)) {
                    $mantenimiento->defectoIdentificados()->create([
                        ...$defecto,
                        'tenant_id' => $mantenimiento->tenant_id,
                    ]);
                }
            }

            $itemsUsados = collect($request->validated('nuevos_items_usados', []))
                ->filter(fn (array $itemUsado) => filled($itemUsado['item_id'] ?? null));

            // Un ítem sin stock se registra igual (para saber qué falta) pero no cuenta como salida.
            $stocks = Item::query()
                ->whereKey($itemsUsados->pluck('item_id')->all())
                ->conMovimientos()
                ->get()
                ->keyBy('id');

            foreach ($itemsUsados as $itemUsado) {
                $item = $stocks->get((int) $itemUsado['item_id']);
                $precioUnitario = (float) $item?->precio_unitario;

                $mantenimiento->itemMantenimientos()->create([
                    ...$itemUsado,
                    'con_stock' => ($item?->stock ?? 0.0) > 0,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => round((float) $itemUsado['cantidad'] * $precioUnitario, 2),
                    'tenant_id' => $mantenimiento->tenant_id,
                ]);
            }

            foreach ($request->validated('nuevos_comentarios', []) as $comentario) {
                if (filled($comentario)) {
                    $mantenimiento->comentarioMantenimientos()->create([
                        'comentario' => $comentario,
                        'user_id' => $request->user()->id,
                        'tenant_id' => $mantenimiento->tenant_id,
                    ]);
                }
            }

            $nuevasFotos = $request->validated('nuevas_fotos', []);
            $archivosFotos = $request->file('nuevas_fotos', []);

            foreach ($nuevasFotos as $index => $foto) {
                $archivo = $archivosFotos[$index]['archivo'] ?? null;

                if ($archivo instanceof UploadedFile) {
                    $mantenimiento->galeriaMantenimientos()->create([
                        'imagen' => $archivo->store("mantenimientos/{$mantenimiento->tenant_id}/{$mantenimiento->id}", 'public'),
                        'descripcion' => $foto['descripcion'] ?? null,
                        'tenant_id' => $mantenimiento->tenant_id,
                    ]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento actualizado.')]);

        return to_route('mantenimientos.index');
    }

    /**
     * Remove the specified mantenimiento.
     */
    public function destroy(Mantenimiento $mantenimiento): RedirectResponse
    {
        Gate::authorize('delete', $mantenimiento);

        $mantenimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento eliminado.')]);

        return to_route('mantenimientos.index');
    }
}
