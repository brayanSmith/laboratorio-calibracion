<?php

namespace App\Actions\Dashboard;

use App\Concerns\SerieMensual;
use App\Modules\Despachos\Models\Despacho;
use Illuminate\Support\Collection;

/**
 * Equipos despachados (entrega recibida) en el rango y su tiempo de atención: días entre
 * que se recibieron en el ingreso y se entregaron al cliente. Los que no tienen fecha de
 * recepción cuentan como despachados pero no en el promedio, el mínimo ni el máximo.
 *
 * Los filtros son listas dependientes, en este orden: área → laboratorio → magnitud → tipo
 * de equipo. Cada lista ofrece solo lo que tienen los despachos del periodo que cumplen los
 * filtros anteriores.
 */
class CalcularTiemposAtencion
{
    use SerieMensual;

    /**
     * Cada filtro es de selección múltiple: un despacho pasa si coincide con cualquiera de
     * los valores elegidos (una lista vacía no filtra).
     *
     * @param  array{desde: string, hasta: string, areas?: list<int>, laboratorios?: list<int|string>, magnitudes?: list<int>, tipos_equipo?: list<int>}  $filtros
     * @return array{resumen: array{cantidad: int, con_tiempo: int, promedio_dias: float|null, minimo_dias: int|null, maximo_dias: int|null}, equipos: list<array<string, mixed>>, mensual: list<array<string, mixed>>, opciones: array<string, list<array{id: int|string, nombre: string}>>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        ['filas' => $filas, 'opciones' => $opciones] = FiltrosDependientes::aplicar(
            $this->despachosDelPeriodo($tenantId, $filtros['desde'], $filtros['hasta']),
            [
                'areas' => $filtros['areas'] ?? [],
                'laboratorios' => $filtros['laboratorios'] ?? [],
                'magnitudes' => $filtros['magnitudes'] ?? [],
                'tipos_equipo' => $filtros['tipos_equipo'] ?? [],
            ],
        );

        $equipos = $filas
            ->map(fn (array $fila): array => $fila['detalle'])
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
            // Por mes de despacho: cuántos y su tiempo promedio (para el gráfico con el objetivo).
            'mensual' => $this->serieMensual(
                $filtros['desde'],
                $filtros['hasta'],
                $equipos,
                fn (array $equipo): string => substr($equipo['fecha_despacho'], 0, 7),
                function (Collection $delMes): array {
                    $diasDelMes = $delMes->pluck('dias')->reject(fn (?int $valor) => $valor === null);

                    return [
                        'cantidad' => $delMes->count(),
                        'promedio_dias' => $diasDelMes->isEmpty() ? null : round($diasDelMes->avg(), 1),
                    ];
                },
            ),
            'opciones' => $opciones,
        ];
    }

    /**
     * Despachos entregados en el periodo, con lo que usan los filtros y el detalle de la tabla.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function despachosDelPeriodo(int $tenantId, string $desde, string $hasta): Collection
    {
        return Despacho::query()
            ->where('tenant_id', $tenantId)
            ->where('entrega_recibida', true)
            ->whereDate('fecha_despacho', '>=', $desde)
            ->whereDate('fecha_despacho', '<=', $hasta)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.serviciosTerceros:id,orden_trabajo_id,empresa_tercero_id',
                'ordenTrabajo.serviciosTerceros.empresaTercero:id,nombre',
                'ordenTrabajo.equipoProgramacion:id,equipo_id,ingreso_id',
                'ordenTrabajo.equipoProgramacion.ingreso:id,fecha_recepcion',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,numero_serie,tipo_equipo_id,cliente_id,area_id',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.area:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica:id,equipo_id,tipo_magnitud_id',
                'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica.tipoMagnitud:id,nombre',
            ])
            ->get()
            ->map(function (Despacho $despacho): array {
                $programacion = $despacho->ordenTrabajo->equipoProgramacion;
                $equipo = $programacion->equipo;
                $recepcion = $programacion->ingreso?->fecha_recepcion;
                $empresas = $despacho->ordenTrabajo->serviciosTerceros
                    ->pluck('empresaTercero')
                    ->filter()
                    ->unique('id')
                    ->values();
                $laboratorios = $empresas->isEmpty()
                    ? [['id' => FiltrosDependientes::PROPIO, 'nombre' => 'Laboratorio propio']]
                    : $empresas->map(fn ($empresa): array => ['id' => $empresa->id, 'nombre' => $empresa->nombre])->all();

                return [
                    'filtros' => [
                        'areas' => FiltrosDependientes::opcion($equipo->area),
                        'laboratorios' => $laboratorios,
                        'magnitudes' => FiltrosDependientes::opcion($equipo->equipoEspecificacionTecnica?->tipoMagnitud),
                        'tipos_equipo' => FiltrosDependientes::opcion($equipo->tipoEquipo),
                    ],
                    'detalle' => [
                        'despacho_id' => $despacho->id,
                        'orden_trabajo' => $despacho->ordenTrabajo->codigo,
                        'equipo' => $equipo->codigo,
                        'modelo' => $equipo->modelo,
                        'numero_serie' => $equipo->numero_serie,
                        'tipo_equipo' => $equipo->tipoEquipo->nombre,
                        'cliente' => $equipo->cliente?->nombre,
                        'laboratorio' => implode(', ', array_column($laboratorios, 'nombre')),
                        'fecha_recepcion' => $recepcion?->toDateString(),
                        'fecha_despacho' => $despacho->fecha_despacho->toDateString(),
                        'dias' => $recepcion === null ? null : (int) $recepcion->diffInDays($despacho->fecha_despacho),
                    ],
                ];
            });
    }
}
