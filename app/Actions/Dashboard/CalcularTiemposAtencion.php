<?php

namespace App\Actions\Dashboard;

use App\Modules\Despachos\Models\Despacho;
use Illuminate\Database\Eloquent\Builder;

/**
 * Equipos despachados (entrega recibida) en el rango y su tiempo de atención: días entre
 * que se recibieron en el ingreso y se entregaron al cliente. Los que no tienen fecha de
 * recepción cuentan como despachados pero no en el promedio, el mínimo ni el máximo.
 */
class CalcularTiemposAtencion
{
    /**
     * @param  array{desde: string, hasta: string, laboratorio?: string|int|null, tipo_equipo_id?: int|null}  $filtros
     * @return array{resumen: array{cantidad: int, con_tiempo: int, promedio_dias: float|null, minimo_dias: int|null, maximo_dias: int|null}, equipos: list<array<string, mixed>>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        $laboratorio = $filtros['laboratorio'] ?? null;
        $tipoEquipoId = $filtros['tipo_equipo_id'] ?? null;

        $equipos = Despacho::query()
            ->where('tenant_id', $tenantId)
            ->where('entrega_recibida', true)
            ->whereDate('fecha_despacho', '>=', $filtros['desde'])
            ->whereDate('fecha_despacho', '<=', $filtros['hasta'])
            ->when($tipoEquipoId, fn (Builder $query) => $query->whereHas(
                'ordenTrabajo.equipoProgramacion.equipo',
                fn (Builder $equipo) => $equipo->where('tipo_equipo_id', $tipoEquipoId),
            ))
            ->when($laboratorio === 'propio', fn (Builder $query) => $query->whereDoesntHave('ordenTrabajo.serviciosTerceros'))
            ->when($laboratorio !== null && $laboratorio !== 'propio', fn (Builder $query) => $query->whereHas(
                'ordenTrabajo.serviciosTerceros',
                fn (Builder $servicio) => $servicio->where('empresa_tercero_id', $laboratorio),
            ))
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.serviciosTerceros:id,orden_trabajo_id,empresa_tercero_id',
                'ordenTrabajo.serviciosTerceros.empresaTercero:id,nombre',
                'ordenTrabajo.equipoProgramacion:id,equipo_id,ingreso_id',
                'ordenTrabajo.equipoProgramacion.ingreso:id,fecha_recepcion',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,tipo_equipo_id,cliente_id',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
            ])
            ->get()
            ->map(function (Despacho $despacho): array {
                $programacion = $despacho->ordenTrabajo->equipoProgramacion;
                $recepcion = $programacion->ingreso?->fecha_recepcion;
                $terceros = $despacho->ordenTrabajo->serviciosTerceros->pluck('empresaTercero.nombre')->filter()->unique();

                return [
                    'despacho_id' => $despacho->id,
                    'orden_trabajo' => $despacho->ordenTrabajo->codigo,
                    'equipo' => $programacion->equipo->codigo,
                    'modelo' => $programacion->equipo->modelo,
                    'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
                    'cliente' => $programacion->equipo->cliente?->nombre,
                    'laboratorio' => $terceros->isEmpty() ? 'Laboratorio propio' : $terceros->implode(', '),
                    'fecha_recepcion' => $recepcion?->toDateString(),
                    'fecha_despacho' => $despacho->fecha_despacho->toDateString(),
                    'dias' => $recepcion === null ? null : (int) $recepcion->diffInDays($despacho->fecha_despacho),
                ];
            })
            ->sortByDesc('dias')
            ->values();

        $dias = $equipos->pluck('dias')->reject(fn (?int $valor) => $valor === null);

        return [
            'resumen' => [
                'cantidad' => $equipos->count(),
                'con_tiempo' => $dias->count(),
                'promedio_dias' => $dias->isEmpty() ? null : round($dias->avg(), 1),
                'minimo_dias' => $dias->min(),
                'maximo_dias' => $dias->max(),
            ],
            'equipos' => $equipos->all(),
        ];
    }
}
