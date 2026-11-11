<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Http\Requests\StoreIngresoRequest;
use App\Modules\Ingresos\Http\Requests\UpdateEstadoIngresoRequest;
use App\Modules\Ingresos\Http\Requests\UpdateIngresoRequest;
use App\Modules\Ingresos\Models\Ingreso;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IngresoController extends Controller
{
    /**
     * Display the ingresos of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ingreso::class);

        $tenantId = $request->user()->tenant_id;

        $ingresos = self::listar($tenantId);

        return Inertia::render('ingresos/index', [
            'ingresos' => $ingresos,
            'bahias' => Bahia::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesIngreso' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'INGRESO')->orderBy('nombre')->get(['id', 'nombre', 'inactivar_equipo', 'es_motivo_cancelacion']),
            'programacionesPendientes' => self::programacionesPendientes($tenantId),
        ]);
    }

    /**
     * Programaciones preventivas todavía PENDIENTE (las que encuentra el buscador del
     * ingreso), con la bahía de su equipo y su fecha, para que el selector de bahías
     * muestre cuántos equipos de cada una tienen servicio en el Desde/Hasta elegido.
     *
     * @return Collection<int, array{bahia_id: int, equipo_id: int, fecha: string}>
     */
    private static function programacionesPendientes(int $tenantId): Collection
    {
        return EquipoProgramacion::query()
            ->join('equipos', 'equipos.id', '=', 'equipo_programacions.equipo_id')
            ->whereNull('equipos.deleted_at')
            ->where('equipo_programacions.tenant_id', $tenantId)
            ->where('equipo_programacions.tipo_mantenimiento', 'PREVENTIVO')
            ->where('equipo_programacions.estado_programacion', 'PENDIENTE')
            ->whereNotNull('equipo_programacions.fecha_proximo_servicio')
            ->get(['equipos.bahia_id', 'equipo_programacions.equipo_id', 'equipo_programacions.fecha_proximo_servicio'])
            ->map(fn (EquipoProgramacion $programacion) => [
                'bahia_id' => (int) $programacion->getAttribute('bahia_id'),
                'equipo_id' => $programacion->equipo_id,
                'fecha' => $programacion->fecha_proximo_servicio->toDateString(),
            ]);
    }

    /**
     * Ingresos del tenant, listos para la tabla y las modales (ver, editar, recibir);
     * opcionalmente solo aquellos en que llegó un equipo (ver EquipoController::historial()).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function listar(int $tenantId, ?int $equipoId = null): Collection
    {

        return Ingreso::query()
            ->where('tenant_id', $tenantId)
            ->when($equipoId !== null, fn (Builder $query) => $query->whereHas(
                'equipoProgramacion',
                fn (Builder $programacion) => $programacion->where('equipo_id', $equipoId),
            ))
            ->with([
                'bahia:id,nombre',
                'tecnicoRecibe:id,name',
                'clienteEntrega:id,nombre',
                'equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'equipoProgramacion.equipo.cliente:id,nombre',
                'equipoProgramacion.equipo.tipoEquipo:id,nombre',
            ])
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Ingreso $ingreso) => [
                'id' => $ingreso->id,
                'bahia_id' => $ingreso->bahia_id,
                'bahia_nombre' => $ingreso->bahia->nombre,
                'fecha_creacion' => $ingreso->created_at->toDateString(),
                'desde' => $ingreso->desde->toDateString(),
                'hasta' => $ingreso->hasta->toDateString(),
                'fecha_recepcion' => $ingreso->fecha_recepcion?->toDateString(),
                'tecnico_recibe_id' => $ingreso->tecnico_recibe_id,
                'tecnico_recibe_nombre' => $ingreso->tecnicoRecibe?->name,
                'cliente_entrega_id' => $ingreso->cliente_entrega_id,
                'cliente_entrega_nombre' => $ingreso->clienteEntrega?->nombre,
                'firma_url' => $ingreso->firmaUrl(),
                'estado_ingreso' => $ingreso->estado_ingreso,
                'novedad' => $ingreso->novedad,
                'motivo_cancelacion' => $ingreso->motivo_cancelacion,
                'equipo_programaciones' => $ingreso->equipoProgramacion->map(fn (EquipoProgramacion $programacion) => [
                    'id' => $programacion->id,
                    'tipo_servicio' => $programacion->tipo_servicio,
                    'tipo_mantenimiento' => $programacion->tipo_mantenimiento,
                    'falla_detectada' => $programacion->falla_detectada,
                    'fecha_proximo_servicio' => $programacion->fecha_proximo_servicio?->toDateString(),
                    'estado_vencimiento' => $programacion->estado_vencimiento,
                    'estado_programacion' => $programacion->estado_programacion,
                    'agendar' => $programacion->agendar,
                    'ingresado' => $programacion->ingresado,
                    'novedad_ingreso_id' => $programacion->novedad_ingreso_id,
                    'observacion_no_ingreso' => $programacion->observacion_no_ingreso,
                    're_agendar' => $programacion->re_agendar,
                    'datos_re_agendamiento' => $programacion->datos_re_agendamiento,
                    'equipo' => [
                        'id' => $programacion->equipo->id,
                        'codigo' => $programacion->equipo->codigo,
                        'modelo' => $programacion->equipo->modelo,
                        'tipo_equipo' => ['nombre' => $programacion->equipo->tipoEquipo->nombre],
                        'cliente' => ['nombre' => $programacion->equipo->cliente->nombre],
                    ],
                ])->values(),
            ]);
    }

    /**
     * Store the ingresos: uno por cada bahía elegida, con el mismo rango de fechas, para
     * registrar varias bahías en bloque. Cada equipo queda en el ingreso de su bahía.
     */
    public function store(StoreIngresoRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $bahiaIds = $request->validated('bahia_ids');

        DB::transaction(function () use ($request, $tenantId, $bahiaIds) {
            $ingresosPorBahia = collect($bahiaIds)->mapWithKeys(fn (int $bahiaId) => [
                $bahiaId => Ingreso::create([
                    ...$request->safe()->only(['desde', 'hasta']),
                    'bahia_id' => $bahiaId,
                    'tenant_id' => $tenantId,
                ]),
            ]);

            // Si el equipo no está en ninguna de las bahías elegidas (no debería: el buscador
            // solo trae equipos de ellas), queda en el ingreso de la primera.
            $this->guardarCambiosDeEquipos(
                $request,
                fn (int $equipoBahiaId): Ingreso => $ingresosPorBahia[$equipoBahiaId] ?? $ingresosPorBahia->first(),
                $tenantId,
            );
        });

        $mensaje = count($bahiaIds) === 1
            ? __('Ingreso registrado.')
            : __(':cantidad ingresos registrados, uno por bahía.', ['cantidad' => count($bahiaIds)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return to_route('ingresos.index');
    }

    /**
     * Update the bahía, fechas y equipos of the specified ingreso.
     */
    public function update(UpdateIngresoRequest $request, Ingreso $ingreso): RedirectResponse
    {
        DB::transaction(function () use ($request, $ingreso) {
            $ingreso->update($request->safe()->except(['programaciones_actualizadas', 'programaciones_correctivas']));

            $this->guardarCambiosDeEquipos($request, fn (): Ingreso => $ingreso, $ingreso->tenant_id);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ingreso actualizado.')]);

        return to_route('ingresos.index');
    }

    /**
     * Update the estado of the ingreso: recibir equipos (queda RECIBIDO, con técnico,
     * cliente, firma, novedad y el ingresado de cada equipo programado), cancelarlo
     * (queda CANCELADO, con su motivo), o devolverlo a PENDIENTE.
     */
    public function actualizarEstado(UpdateEstadoIngresoRequest $request, Ingreso $ingreso): RedirectResponse
    {
        $data = $request->safe()->except(['firma_cliente_entrega', 'eliminar_firma', 'equipos_recibidos', 'novedad_cancelacion_id']);

        if ($request->hasFile('firma_cliente_entrega')) {
            $this->deleteFirma($ingreso);
            $data['firma_cliente_entrega'] = $request->file('firma_cliente_entrega')->store("firmas/{$ingreso->tenant_id}", 'public');
        } elseif ($request->boolean('eliminar_firma')) {
            $this->deleteFirma($ingreso);
            $data['firma_cliente_entrega'] = null;
        }

        // La fecha de recepción no se escribe: se fija el día en que el ingreso pasa a RECIBIDO
        // y no vuelve a cambiar, aunque luego se edite o se reciba de nuevo.
        if ($data['estado_ingreso'] === 'RECIBIDO' && $ingreso->fecha_recepcion === null) {
            $data['fecha_recepcion'] = now()->toDateString();
        }

        DB::transaction(function () use ($request, $ingreso, $data) {
            $ingreso->update($data);

            if ($data['estado_ingreso'] === 'CANCELADO') {
                $this->cancelarEquiposDelIngreso(
                    $ingreso,
                    $request->validated('novedad_cancelacion_id'),
                    $data['motivo_cancelacion'],
                    $request->boolean('re_agendar_equipos') ? $request->validated('fecha_re_agendamiento') : null,
                );
            } else {
                $this->guardarEquiposRecibidos($request, $ingreso);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Estado del ingreso actualizado.')]);

        return back();
    }

    /**
     * Remove the specified ingreso.
     */
    public function destroy(Ingreso $ingreso): RedirectResponse
    {
        Gate::authorize('delete', $ingreso);

        if ($ingreso->ordenesTrabajo()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un ingreso que tiene órdenes de trabajo asociadas.'),
            ]);

            return back();
        }

        DB::transaction(function () use ($ingreso) {
            $this->liberarEquiposDelIngreso($ingreso);

            $this->deleteFirma($ingreso);
            $ingreso->update(['firma_cliente_entrega' => null]);
            $ingreso->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ingreso eliminado.')]);

        return to_route('ingresos.index');
    }

    /**
     * Release the equipo-programaciones linked to an ingreso that's about to be
     * deleted. Preventivo ones go back to PENDIENTE and unlinked, so they show up
     * again in a new search for the same bahía and rango de fechas; correctivo ones
     * only existed for this ingreso, so they're removed along with it.
     */
    private function liberarEquiposDelIngreso(Ingreso $ingreso): void
    {
        $ingreso->equipoProgramacion()
            ->where('tipo_mantenimiento', 'CORRECTIVO')
            ->delete();

        $ingreso->equipoProgramacion()
            ->where('tipo_mantenimiento', 'PREVENTIVO')
            ->get()
            ->each(function (EquipoProgramacion $programacion): void {
                $programacion->update([
                    'estado_programacion' => 'PENDIENTE',
                    'agendar' => true,
                    'ingresado' => true,
                    'novedad_ingreso_id' => null,
                    'observacion_no_ingreso' => null,
                    're_agendar' => false,
                    'datos_re_agendamiento' => null,
                    'ingreso_id' => null,
                ]);
                // Vuelve a estar pendiente ella misma: se quita la re-agendada que aún no se agendó.
                $programacion->sincronizarReAgendamiento();
            });
    }

    /**
     * Apply the pending equipo-programacion changes gathered by the ingreso form's
     * buscador, linking every preventivo marcado con agendar = true to the ingreso of its
     * equipo's bahía (see $ingresoDeBahia), y liberando (sin enlazar) los que quedaron con
     * agendar = false, para que sigan disponibles en una búsqueda futura.
     *
     * @param  Closure(int): Ingreso  $ingresoDeBahia  el ingreso que recibe los equipos de esa bahía
     */
    private function guardarCambiosDeEquipos(StoreIngresoRequest|UpdateIngresoRequest $request, Closure $ingresoDeBahia, int $tenantId): void
    {
        foreach ($request->validated('programaciones_actualizadas', []) as $cambio) {
            $agendar = $cambio['agendar'] ?? true;
            $programacion = EquipoProgramacion::query()
                ->with('equipo:id,bahia_id')
                ->where('id', $cambio['id'])
                ->where('tenant_id', $tenantId)
                ->first();

            $programacion?->update([
                'estado_programacion' => $agendar ? 'AGENDADO' : 'PENDIENTE',
                'agendar' => $agendar,
                'ingreso_id' => $agendar ? $ingresoDeBahia($programacion->equipo->bahia_id)->id : null,
            ]);
        }

        foreach ($request->validated('programaciones_correctivas', []) as $correctivo) {
            $this->crearProgramacionCorrectiva($correctivo, $ingresoDeBahia, $tenantId, 'AGENDADO');
        }
    }

    /**
     * Apply the "ingresado" decision gathered by "Recibir equipos" for each equipo
     * programado ya enlazado a este ingreso, y crea los equipos correctivos anotados
     * de último momento (ver AgregarEquipoCorrectivo). Si ingresado es false, se trata
     * como un cancelado: guarda la novedad y, si corresponde, el re-agendamiento.
     */
    private function guardarEquiposRecibidos(UpdateEstadoIngresoRequest $request, Ingreso $ingreso): void
    {
        foreach ($request->validated('equipos_recibidos', []) as $recibido) {
            $ingresado = $recibido['ingresado'] ?? true;
            $reAgendar = ! $ingresado && ($recibido['re_agendar'] ?? false);

            $programacion = EquipoProgramacion::query()
                ->where('id', $recibido['id'])
                ->where('tenant_id', $ingreso->tenant_id)
                ->first();

            $programacion?->update([
                'ingresado' => $ingresado,
                'estado_programacion' => match (true) {
                    $ingresado => 'RECIBIDO',
                    $reAgendar => 'RE_AGENDADO',
                    default => 'CANCELADO',
                },
                'novedad_ingreso_id' => $recibido['novedad_ingreso_id'] ?? null,
                'observacion_no_ingreso' => $recibido['observacion_no_ingreso'] ?? null,
                're_agendar' => $reAgendar,
                'datos_re_agendamiento' => $reAgendar ? $recibido['datos_re_agendamiento'] : null,
            ]);
            $programacion?->sincronizarReAgendamiento();
        }

        foreach ($request->validated('equipos_correctivos_recibidos', []) as $correctivo) {
            $this->crearProgramacionCorrectiva($correctivo, $ingreso, $ingreso->tenant_id, 'RECIBIDO');
        }
    }

    /**
     * Al cancelar el ingreso, cancela de una vez todos sus equipos (en vez de uno por
     * uno al recibir): quedan como no ingresados, con la novedad elegida y el motivo de
     * cancelación como observación. Si se re-agendan (por lote, todos para la misma
     * fecha), cada uno queda disponible de nuevo para esa fecha (ver
     * EquipoProgramacion::sincronizarReAgendamiento()). Se actualizan modelo a modelo
     * para que corran el seguimiento y la inactivación por novedad.
     */
    private function cancelarEquiposDelIngreso(Ingreso $ingreso, ?int $novedadId, ?string $motivo, ?string $fechaReAgendamiento): void
    {
        $ingreso->equipoProgramacion()
            ->get()
            ->each(function (EquipoProgramacion $programacion) use ($novedadId, $motivo, $fechaReAgendamiento): void {
                $programacion->update([
                    'ingresado' => false,
                    'estado_programacion' => $fechaReAgendamiento ? 'RE_AGENDADO' : 'CANCELADO',
                    'novedad_ingreso_id' => $novedadId,
                    'observacion_no_ingreso' => $motivo,
                    're_agendar' => $fechaReAgendamiento !== null,
                    'datos_re_agendamiento' => $fechaReAgendamiento ? ['fecha_proximo_agendamiento' => $fechaReAgendamiento] : null,
                ]);
                $programacion->sincronizarReAgendamiento();
            });
    }

    /**
     * Create a correctivo equipo-programacion (sin servicio programado, detectado al
     * vuelo) already linked and agendado for the given ingreso.
     *
     * @param  array<string, mixed>  $correctivo
     */
    private function crearProgramacionCorrectiva(array $correctivo, Ingreso|Closure $ingreso, int $tenantId, string $estadoProgramacion): void
    {
        $equipo = Equipo::with('tipoEquipo:id,tipo_mantenimiento')
            ->where('id', $correctivo['equipo_id'])
            ->firstOrFail();

        if ($ingreso instanceof Closure) {
            $ingreso = $ingreso($equipo->bahia_id);
        }

        $equipo->equipoProgramaciones()->create([
            'tipo_servicio' => implode(',', $equipo->tipoEquipo->tiposServicio()),
            'tipo_mantenimiento' => 'CORRECTIVO',
            'falla_detectada' => $correctivo['falla_detectada'],
            'estado_programacion' => $estadoProgramacion,
            'ingresado' => true,
            'ingreso_id' => $ingreso->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Delete the signature file of the ingreso from storage, if it has one.
     */
    private function deleteFirma(Ingreso $ingreso): void
    {
        if ($ingreso->firma_cliente_entrega) {
            Storage::disk('public')->delete($ingreso->firma_cliente_entrega);
        }
    }
}
