<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ingresos\StoreIngresoRequest;
use App\Http\Requests\Ingresos\UpdateEstadoIngresoRequest;
use App\Http\Requests\Ingresos\UpdateIngresoRequest;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\EmpresaTercero;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use App\Models\Ingreso;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $ingresos = Ingreso::query()
            ->where('tenant_id', $tenantId)
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
                'desde' => $ingreso->desde->toDateString(),
                'hasta' => $ingreso->hasta->toDateString(),
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

        return Inertia::render('ingresos/index', [
            'ingresos' => $ingresos,
            'bahias' => Bahia::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesIngreso' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'INGRESO')->orderBy('nombre')->get(['id', 'nombre']),
            'empresasTerceras' => EmpresaTercero::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),

            // Para el globito de conteo en los botones "Agendar ..." (ver
            // OrdenTrabajoController::equiposListos()/equiposListosCalibracion()/despachosListos()).
            'equiposListosCount' => EquipoProgramacion::query()->listosParaMantenimiento($tenantId)->count(),
            'equiposListosCalibracionCount' => OrdenTrabajo::query()->listosParaCalibracion($tenantId)->count(),
            'despachosListosCount' => Despacho::query()->listosParaAgendar($tenantId)->count(),
        ]);
    }

    /**
     * Store a newly created ingreso.
     */
    public function store(StoreIngresoRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        DB::transaction(function () use ($request, $tenantId) {
            $ingreso = Ingreso::create([
                ...$request->safe()->except(['programaciones_actualizadas', 'programaciones_correctivas']),
                'tenant_id' => $tenantId,
            ]);

            $this->guardarCambiosDeEquipos($request, $ingreso, $tenantId);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ingreso registrado.')]);

        return to_route('ingresos.index');
    }

    /**
     * Update the bahía, fechas y equipos of the specified ingreso.
     */
    public function update(UpdateIngresoRequest $request, Ingreso $ingreso): RedirectResponse
    {
        DB::transaction(function () use ($request, $ingreso) {
            $ingreso->update($request->safe()->except(['programaciones_actualizadas', 'programaciones_correctivas']));

            $this->guardarCambiosDeEquipos($request, $ingreso, $ingreso->tenant_id);
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
        $data = $request->safe()->except(['firma_cliente_entrega', 'eliminar_firma', 'equipos_recibidos']);

        if ($request->hasFile('firma_cliente_entrega')) {
            $this->deleteFirma($ingreso);
            $data['firma_cliente_entrega'] = $request->file('firma_cliente_entrega')->store("firmas/{$ingreso->tenant_id}", 'public');
        } elseif ($request->boolean('eliminar_firma')) {
            $this->deleteFirma($ingreso);
            $data['firma_cliente_entrega'] = null;
        }

        DB::transaction(function () use ($request, $ingreso, $data) {
            $ingreso->update($data);

            $this->guardarEquiposRecibidos($request, $ingreso);
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
            ->update([
                'estado_programacion' => 'PENDIENTE',
                'agendar' => true,
                'ingresado' => true,
                'novedad_ingreso_id' => null,
                'observacion_no_ingreso' => null,
                're_agendar' => false,
                'datos_re_agendamiento' => null,
                'ingreso_id' => null,
            ]);
    }

    /**
     * Apply the pending equipo-programacion changes gathered by the ingreso form's
     * buscador, linking to this ingreso every preventivo marcado con agendar = true, y
     * liberando (sin enlazar) los que quedaron con agendar = false, para que sigan
     * disponibles en una búsqueda futura.
     */
    private function guardarCambiosDeEquipos(StoreIngresoRequest|UpdateIngresoRequest $request, Ingreso $ingreso, int $tenantId): void
    {
        foreach ($request->validated('programaciones_actualizadas', []) as $cambio) {
            $agendar = $cambio['agendar'] ?? true;

            EquipoProgramacion::query()
                ->where('id', $cambio['id'])
                ->where('tenant_id', $tenantId)
                ->update([
                    'estado_programacion' => $agendar ? 'AGENDADO' : 'PENDIENTE',
                    'agendar' => $agendar,
                    'ingreso_id' => $agendar ? $ingreso->id : null,
                ]);
        }

        foreach ($request->validated('programaciones_correctivas', []) as $correctivo) {
            $this->crearProgramacionCorrectiva($correctivo, $ingreso, $tenantId);
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

            EquipoProgramacion::query()
                ->where('id', $recibido['id'])
                ->where('tenant_id', $ingreso->tenant_id)
                ->update([
                    'ingresado' => $ingresado,
                    'estado_programacion' => $ingresado ? 'AGENDADO' : 'CANCELADO',
                    'novedad_ingreso_id' => $recibido['novedad_ingreso_id'] ?? null,
                    'observacion_no_ingreso' => $recibido['observacion_no_ingreso'] ?? null,
                    're_agendar' => $reAgendar,
                    'datos_re_agendamiento' => $reAgendar ? $recibido['datos_re_agendamiento'] : null,
                ]);
        }

        foreach ($request->validated('equipos_correctivos_recibidos', []) as $correctivo) {
            $this->crearProgramacionCorrectiva($correctivo, $ingreso, $ingreso->tenant_id);
        }
    }

    /**
     * Create a correctivo equipo-programacion (sin servicio programado, detectado al
     * vuelo) already linked and agendado for the given ingreso.
     *
     * @param  array<string, mixed>  $correctivo
     */
    private function crearProgramacionCorrectiva(array $correctivo, Ingreso $ingreso, int $tenantId): void
    {
        $equipo = Equipo::with('tipoEquipo:id,tipo_mantenimiento')
            ->where('id', $correctivo['equipo_id'])
            ->firstOrFail();

        $tipoServicio = $equipo->tipoEquipo->tipo_mantenimiento === 'A'
            ? ['MANTENIMIENTO', 'CALIBRACION']
            : ['MANTENIMIENTO'];

        $equipo->equipoProgramaciones()->create([
            'tipo_servicio' => implode(',', $tipoServicio),
            'tipo_mantenimiento' => 'CORRECTIVO',
            'falla_detectada' => $correctivo['falla_detectada'],
            'estado_programacion' => 'AGENDADO',
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
