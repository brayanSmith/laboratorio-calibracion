<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mantenimientos\FinalizarMantenimientoRequest;
use App\Http\Requests\Mantenimientos\UpdateMantenimientoRequest;
use App\Models\ComentarioMantenimiento;
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
use Inertia\Inertia;
use Inertia\Response;

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

        $mantenimientos = Mantenimiento::query()
            ->where('tenant_id', $tenantId)
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
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'novedadesMantenimiento' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->orderBy('nombre')->get(['id', 'nombre']),
            'items' => Item::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    /**
     * Start the mantenimiento: opens a tiempo_servicio with estado_tiempo = INICIO y sin
     * fin, para medir cuánto dura. Se puede volver a iniciar más adelante (ej. tras una
     * pausa), así que no valida que no haya uno ya abierto.
     */
    public function iniciar(Mantenimiento $mantenimiento): RedirectResponse
    {
        Gate::authorize('update', $mantenimiento);

        TiempoServicio::create([
            'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
            'tipo_servicio' => 'MANTENIMIENTO',
            'inicio' => now(),
            'estado_tiempo' => 'INICIO',
            'es_tercero' => false,
            'tenant_id' => $mantenimiento->tenant_id,
        ]);

        return back();
    }

    /**
     * Finish the mantenimiento: guarda el estado final del equipo y si quedó firmado,
     * marca el mantenimiento como FINALIZADO, y cierra el tiempo_servicio que estaba
     * en curso (fin = ahora, duración calculada, estado_tiempo = FIN).
     */
    public function finalizar(FinalizarMantenimientoRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        DB::transaction(function () use ($request, $mantenimiento): void {
            $mantenimiento->update([
                'estado_final_equipo' => $request->validated('estado_final_equipo'),
                'firmado' => $request->validated('firmado'),
                'estado_mantenimiento' => 'FINALIZADO',
            ]);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mantenimiento finalizado.')]);

        return to_route('mantenimientos.index');
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

            foreach ($request->validated('nuevos_items_usados', []) as $itemUsado) {
                if (filled($itemUsado['item_id'] ?? null)) {
                    $mantenimiento->itemMantenimientos()->create([
                        ...$itemUsado,
                        'tenant_id' => $mantenimiento->tenant_id,
                    ]);
                }
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
