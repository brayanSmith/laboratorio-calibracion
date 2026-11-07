<?php

namespace App\Actions\Dashboard;

use App\Concerns\SerieMensual;
use App\Modules\Equipos\Models\EquipoProgramacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cumplimiento de lo programado: de los equipos con servicio preventivo programado en el
 * rango, cuántos se atendieron, es decir, terminaron su mantenimiento o calibración
 * (propio o de tercero). Un equipo con varias programaciones en el rango cuenta una vez, y
 * como atendido si se atendió alguna.
 *
 * Los filtros son listas dependientes de selección múltiple (ver FiltrosDependientes), en
 * este orden: área → magnitud → tipo de equipo. Además se elige el tipo de mantenimiento
 * del tipo de equipo: A (mantenimiento y calibración), B (solo mantenimiento) o los dos.
 */
class CalcularCumplimiento
{
    use SerieMensual;

    /** Estados con los que una calibración queda terminada. */
    private const CALIBRACION_TERMINADA = ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

    /** Máximo de equipos pendientes que se listan. */
    private const MAX_PENDIENTES = 100;

    /** Tipos de mantenimiento del tipo de equipo: A (mantenimiento y calibración) o B (solo mantenimiento). */
    public const TIPOS_MANTENIMIENTO = ['A', 'B'];

    /**
     * @param  array{desde: string, hasta: string, areas?: list<int>, magnitudes?: list<int>, tipos_equipo?: list<int>, tipos_mantenimiento?: list<string>}  $filtros
     * @return array{resumen: array{programados: int, atendidos: int, pendientes: int, porcentaje: float|null}, por_area: list<array<string, mixed>>, mensual: list<array<string, mixed>>, pendientes: list<array<string, mixed>>, opciones: array<string, list<array{id: int|string, nombre: string}>>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        // A, B o los dos (sin elegir, los dos).
        $tiposMantenimiento = ($filtros['tipos_mantenimiento'] ?? []) ?: self::TIPOS_MANTENIMIENTO;

        $programaciones = EquipoProgramacion::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_mantenimiento', 'PREVENTIVO')
            ->whereDate('fecha_proximo_servicio', '>=', $filtros['desde'])
            ->whereDate('fecha_proximo_servicio', '<=', $filtros['hasta'])
            ->whereHas('equipo.tipoEquipo', fn (Builder $tipo) => $tipo->whereIn('tipo_mantenimiento', $tiposMantenimiento))
            ->withExists(['ordenesTrabajo as atendida' => fn (Builder $orden) => $orden->where(fn (Builder $servicio) => $servicio
                ->whereHas('mantenimientos', fn (Builder $q) => $q->where('estado_mantenimiento', 'FINALIZADO'))
                ->orWhereHas('calibraciones', fn (Builder $q) => $q->whereIn('estado_calibracion', self::CALIBRACION_TERMINADA))
                ->orWhereHas('serviciosTerceros', fn (Builder $q) => $q->where('estado_servicio', 'FINALIZADO')))])
            ->with([
                'equipo:id,codigo,modelo,area_id,tipo_equipo_id',
                'equipo.area:id,nombre',
                'equipo.tipoEquipo:id,nombre',
                'equipo.equipoEspecificacionTecnica:id,equipo_id,tipo_magnitud_id',
                'equipo.equipoEspecificacionTecnica.tipoMagnitud:id,nombre',
            ])
            ->orderBy('fecha_proximo_servicio')
            ->get();

        // Un equipo por fila: atendido si se atendió alguna de sus programaciones del rango.
        $delPeriodo = $programaciones
            ->groupBy('equipo_id')
            ->map(function (Collection $deUnEquipo): array {
                $equipo = $deUnEquipo->first()->equipo;

                return [
                    'equipo' => $equipo,
                    'fecha_programada' => $deUnEquipo->first()->fecha_proximo_servicio->toDateString(),
                    'estado_programacion' => $deUnEquipo->first()->estado_programacion,
                    'atendido' => $deUnEquipo->contains(fn (EquipoProgramacion $programacion) => (bool) $programacion->getAttribute('atendida')),
                    'filtros' => [
                        'areas' => FiltrosDependientes::opcion($equipo->area),
                        'magnitudes' => FiltrosDependientes::opcion($equipo->equipoEspecificacionTecnica?->tipoMagnitud),
                        'tipos_equipo' => FiltrosDependientes::opcion($equipo->tipoEquipo),
                    ],
                ];
            })
            ->values();

        ['filas' => $equipos, 'opciones' => $opciones] = FiltrosDependientes::aplicar($delPeriodo, [
            'areas' => $filtros['areas'] ?? [],
            'magnitudes' => $filtros['magnitudes'] ?? [],
            'tipos_equipo' => $filtros['tipos_equipo'] ?? [],
        ]);

        $atendidos = $equipos->where('atendido', true)->count();

        return [
            'resumen' => [
                'programados' => $equipos->count(),
                'atendidos' => $atendidos,
                'pendientes' => $equipos->count() - $atendidos,
                'porcentaje' => $this->porcentaje($atendidos, $equipos->count()),
            ],
            'por_area' => $equipos
                ->groupBy(fn (array $fila): string => $fila['equipo']->area?->nombre ?? 'Sin área')
                ->map(fn (Collection $deUnArea, string $area): array => [
                    'area' => $area,
                    'programados' => $deUnArea->count(),
                    'atendidos' => $deUnArea->where('atendido', true)->count(),
                    'porcentaje' => $this->porcentaje($deUnArea->where('atendido', true)->count(), $deUnArea->count()),
                ])
                ->sortBy('area')
                ->values()
                ->all(),
            // Por mes programado: programados, atendidos y porcentaje (para el gráfico con el objetivo).
            'mensual' => $this->serieMensual(
                $filtros['desde'],
                $filtros['hasta'],
                $equipos->values(),
                fn (array $fila): string => substr($fila['fecha_programada'], 0, 7),
                fn (Collection $delMes): array => [
                    'programados' => $delMes->count(),
                    'atendidos' => $delMes->where('atendido', true)->count(),
                    'porcentaje' => $this->porcentaje($delMes->where('atendido', true)->count(), $delMes->count()),
                ],
            ),
            'pendientes' => $equipos
                ->where('atendido', false)
                ->take(self::MAX_PENDIENTES)
                ->map(fn (array $fila): array => [
                    'equipo_id' => $fila['equipo']->id,
                    'equipo' => $fila['equipo']->codigo,
                    'modelo' => $fila['equipo']->modelo,
                    'tipo_equipo' => $fila['equipo']->tipoEquipo?->nombre,
                    'area' => $fila['equipo']->area?->nombre,
                    'fecha_programada' => $fila['fecha_programada'],
                    'estado_programacion' => $fila['estado_programacion'],
                ])
                ->values()
                ->all(),
            'opciones' => $opciones,
        ];
    }

    private function porcentaje(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte * 100 / $total, 1);
    }
}
