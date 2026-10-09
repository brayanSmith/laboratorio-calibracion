<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicioTerceros\FinalizarServicioTerceroRequest;
use App\Http\Requests\ServicioTerceros\UpdateServicioTerceroRequest;
use App\Models\Despacho;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\TiempoServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ServicioTerceroController extends Controller
{
    /**
     * Servicios de tercero del tenant de un tipo (MANTENIMIENTO o CALIBRACION), listos
     * para las tablas "Tercero" de Mantenimientos y Calibraciones, con el tiempo_servicio
     * abierto de cada uno (si ya se inició).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function listar(int $tenantId, string $tipoServicio): Collection
    {
        $tiemposActivos = TiempoServicio::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', $tipoServicio)
            ->where('es_tercero', true)
            ->where('estado_tiempo', 'INICIO')
            ->whereNull('fin')
            ->get(['orden_trabajo_id', 'inicio'])
            ->keyBy('orden_trabajo_id');

        return ServicioTercero::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_servicio', $tipoServicio)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'empresaTercero:id,nombre',
            ])
            ->latest()
            ->get()
            ->map(fn (ServicioTercero $servicio) => [
                'id' => $servicio->id,
                'fecha' => $servicio->created_at->toDateString(),
                'orden_trabajo_codigo' => $servicio->ordenTrabajo->codigo,
                'equipo_codigo' => $servicio->ordenTrabajo->equipoProgramacion->equipo->codigo,
                'equipo_modelo' => $servicio->ordenTrabajo->equipoProgramacion->equipo->modelo,
                'cliente_nombre' => $servicio->ordenTrabajo->equipoProgramacion->equipo->cliente?->nombre,
                'empresa_tercero_id' => $servicio->empresa_tercero_id,
                'empresa_tercero_nombre' => $servicio->empresaTercero->nombre,
                'pdf_url' => $servicio->pdfUrl(),
                'tiempo_servicio_inicio' => $tiemposActivos->get($servicio->orden_trabajo_id)?->inicio->toIso8601String(),
                'estado_final_equipo' => $servicio->estado_final_equipo,
                're_agendar' => $servicio->re_agendar,
            ]);
    }

    /**
     * Start the servicio de tercero: opens a tiempo_servicio (es_tercero = true) with
     * estado_tiempo = INICIO y sin fin, para medir cuánto dura. Se puede volver a
     * iniciar más adelante, así que no valida que no haya uno ya abierto.
     */
    public function iniciar(ServicioTercero $servicioTercero): RedirectResponse
    {
        Gate::authorize('update', $servicioTercero);

        TiempoServicio::create([
            'orden_trabajo_id' => $servicioTercero->orden_trabajo_id,
            'tipo_servicio' => $servicioTercero->tipo_servicio,
            'inicio' => now(),
            'estado_tiempo' => 'INICIO',
            'es_tercero' => true,
            'tenant_id' => $servicioTercero->tenant_id,
        ]);

        return back();
    }

    /**
     * Update the servicio de tercero: la empresa que lo realiza y, si se sube, el
     * documento (PDF) del servicio, que reemplaza al anterior.
     */
    public function update(UpdateServicioTerceroRequest $request, ServicioTercero $servicioTercero): RedirectResponse
    {
        $datos = ['empresa_tercero_id' => $request->validated('empresa_tercero_id')];

        if ($request->hasFile('pdf_servicio')) {
            $servicioTercero->deletePdf();

            $datos['pdf_servicio'] = $request->file('pdf_servicio')
                ->store("servicios-terceros/{$servicioTercero->tenant_id}/{$servicioTercero->id}", 'public');
        }

        $servicioTercero->update($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio de tercero actualizado.')]);

        return back();
    }

    /**
     * Finish the servicio de tercero: guarda el documento (PDF) del servicio y el
     * estado final del equipo, y cierra el tiempo_servicio en curso. Si quedó APROBADO
     * crea el despacho de la orden de trabajo (sin técnico todavía) para "Agendar
     * Despachos" (ver OrdenTrabajoController::despachosListos()) y, si el servicio es de
     * calibración, marca la orden como calibracion_finalizado. Si quedó RECHAZADO y se
     * pidió re-agendar, crea una nueva orden de trabajo para el mismo equipo (sin agendar
     * todavía) para que vuelva a aparecer en "Agendar Mantenimiento"; si el servicio es de
     * calibración, la nueva orden se marca como devolución, igual que al devolver una
     * calibración local a mantenimiento.
     */
    public function finalizar(FinalizarServicioTerceroRequest $request, ServicioTercero $servicioTercero): RedirectResponse
    {
        DB::transaction(function () use ($request, $servicioTercero): void {
            $estadoFinal = $request->validated('estado_final_equipo');
            $reAgendar = $estadoFinal === 'RECHAZADO' && $request->validated('re_agendar');

            $servicioTercero->deletePdf();

            $servicioTercero->update([
                'estado_final_equipo' => $estadoFinal,
                're_agendar' => $reAgendar,
                'pdf_servicio' => $request->file('pdf_servicio')
                    ->store("servicios-terceros/{$servicioTercero->tenant_id}/{$servicioTercero->id}", 'public'),
            ]);

            if ($estadoFinal === 'APROBADO') {
                if ($servicioTercero->tipo_servicio === 'CALIBRACION') {
                    $servicioTercero->ordenTrabajo->update(['calibracion_finalizado' => true]);
                }

                Despacho::create([
                    'orden_trabajo_id' => $servicioTercero->orden_trabajo_id,
                    'tenant_id' => $servicioTercero->tenant_id,
                ]);
            }

            if ($reAgendar) {
                $siguienteNumero = OrdenTrabajo::withTrashed()->where('tenant_id', $servicioTercero->tenant_id)->count() + 1;

                OrdenTrabajo::create([
                    'codigo' => 'OT-'.str_pad((string) $siguienteNumero, 4, '0', STR_PAD_LEFT),
                    'equipo_programacion_id' => $servicioTercero->ordenTrabajo->equipo_programacion_id,
                    'fecha_programada_orden_trabajo' => now()->toDateString(),
                    'estado' => 'EN_BAHIA',
                    'devolucion' => $servicioTercero->tipo_servicio === 'CALIBRACION',
                    'tenant_id' => $servicioTercero->tenant_id,
                ]);
            }

            $tiempoServicio = TiempoServicio::query()
                ->where('orden_trabajo_id', $servicioTercero->orden_trabajo_id)
                ->where('tipo_servicio', $servicioTercero->tipo_servicio)
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio de tercero finalizado.')]);

        return back();
    }

    /**
     * Remove the specified servicio de tercero.
     */
    public function destroy(ServicioTercero $servicioTercero): RedirectResponse
    {
        Gate::authorize('delete', $servicioTercero);

        $servicioTercero->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio de tercero eliminado.')]);

        return back();
    }
}
