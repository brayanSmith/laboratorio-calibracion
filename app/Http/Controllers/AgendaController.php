<?php

namespace App\Http\Controllers;

use App\Enums\TenantPermission;
use App\Models\Calibracion;
use App\Models\Despacho;
use App\Models\Mantenimiento;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgendaController extends Controller
{
    /**
     * List, as calendar events, the mantenimientos, calibraciones and despachos that are
     * still pending. All of them are returned at once (no date range) so the calendar can
     * keep them on the device and navigate months or filter by técnico without asking the
     * server again. Each type is only included when the user can view its module.
     * Despachos appear on their fecha_programada and are skipped while it is empty.
     */
    public function eventos(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::AgendaVer->value), 403);

        $usuario = $request->user();
        $tenantId = $usuario->tenant_id;
        $soloPropias = ! $usuario->can(TenantPermission::AgendaVerTodos->value);

        $eventos = collect();

        if (Gate::allows('viewAny', Mantenimiento::class)) {
            $eventos = $eventos->concat(
                Mantenimiento::query()
                    ->where('tenant_id', $tenantId)
                    ->where('estado_mantenimiento', '!=', 'FINALIZADO')
                    ->when($soloPropias, fn ($query) => $query->where('tecnico_id', $usuario->id))
                    ->with(['tecnico:id,name', 'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre'])
                    ->get()
                    ->map(fn (Mantenimiento $mantenimiento) => $this->evento(
                        tipo: 'MANTENIMIENTO',
                        id: $mantenimiento->id,
                        fecha: $mantenimiento->fecha_mantenimiento,
                        estado: $mantenimiento->estado_mantenimiento,
                        ordenTrabajo: $mantenimiento->ordenTrabajo,
                        tecnico: $mantenimiento->tecnico,
                        ruta: route('mantenimientos.index'),
                    )),
            );
        }

        if (Gate::allows('viewAny', Calibracion::class)) {
            $eventos = $eventos->concat(
                Calibracion::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('estado_calibracion', ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO'])
                    ->when($soloPropias, fn ($query) => $query->where('tecnico_id', $usuario->id))
                    ->with(['tecnico:id,name', 'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre'])
                    ->get()
                    ->map(fn (Calibracion $calibracion) => $this->evento(
                        tipo: 'CALIBRACION',
                        id: $calibracion->id,
                        fecha: $calibracion->fecha_calibracion,
                        estado: $calibracion->estado_calibracion,
                        ordenTrabajo: $calibracion->ordenTrabajo,
                        tecnico: $calibracion->tecnico,
                        ruta: route('calibraciones.index'),
                    )),
            );
        }

        if (Gate::allows('viewAny', Despacho::class)) {
            $eventos = $eventos->concat(
                Despacho::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('tecnico_entrega_id')
                    ->when($soloPropias, fn ($query) => $query->where('tecnico_entrega_id', $usuario->id))
                    ->where('entrega_recibida', false)
                    ->whereNotNull('fecha_programada')
                    ->with(['tecnicoEntrega:id,name', 'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre'])
                    ->get()
                    ->map(fn (Despacho $despacho) => $this->evento(
                        tipo: 'DESPACHO',
                        id: $despacho->id,
                        fecha: $despacho->fecha_programada,
                        estado: $despacho->estado_despacho,
                        ordenTrabajo: $despacho->ordenTrabajo,
                        tecnico: $despacho->tecnicoEntrega,
                        ruta: route('despachos.index'),
                    )),
            );
        }

        return response()->json($eventos->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function evento(string $tipo, int $id, CarbonInterface $fecha, string $estado, mixed $ordenTrabajo, ?User $tecnico, string $ruta): array
    {
        $equipo = $ordenTrabajo->equipoProgramacion->equipo;

        return [
            'id' => "{$tipo}-{$id}",
            'start' => $fecha->toDateString(),
            'allDay' => true,
            'tipo' => $tipo,
            'estado' => $estado,
            'orden_trabajo' => $ordenTrabajo->codigo,
            'equipo' => $equipo->codigo,
            'modelo' => $equipo->modelo,
            'cliente' => $equipo->cliente?->nombre,
            'tecnico' => $tecnico?->name,
            'tecnico_id' => $tecnico?->id,
            'ruta' => $ruta,
        ];
    }
}
