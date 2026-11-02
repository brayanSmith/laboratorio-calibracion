<?php

namespace App\Actions\Dashboard;

use App\Modules\Equipos\Models\EquipoProgramacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cumplimiento de lo programado: de los equipos con servicio preventivo programado en el
 * rango, cuántos se atendieron, es decir, terminaron su mantenimiento o calibración
 * (propio o de tercero). Un equipo con varias programaciones en el rango cuenta una vez, y
 * como atendido si se atendió alguna.
 */
class CalcularCumplimiento
{
    /** Estados con los que una calibración queda terminada. */
    private const CALIBRACION_TERMINADA = ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

    /** Máximo de equipos pendientes que se listan. */
    private const MAX_PENDIENTES = 100;

    /**
     * @param  array{desde: string, hasta: string, area_id?: int|null, tipo_equipo_id?: int|null}  $filtros
     * @return array{resumen: array{programados: int, atendidos: int, pendientes: int, porcentaje: float|null}, por_area: list<array<string, mixed>>, pendientes: list<array<string, mixed>>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        $programaciones = EquipoProgramacion::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_mantenimiento', 'PREVENTIVO')
            ->whereDate('fecha_proximo_servicio', '>=', $filtros['desde'])
            ->whereDate('fecha_proximo_servicio', '<=', $filtros['hasta'])
            ->whereHas('equipo', fn (Builder $equipo) => $equipo
                ->when($filtros['area_id'] ?? null, fn (Builder $q, int $areaId) => $q->where('area_id', $areaId))
                ->when($filtros['tipo_equipo_id'] ?? null, fn (Builder $q, int $tipoEquipoId) => $q->where('tipo_equipo_id', $tipoEquipoId)))
            ->withExists(['ordenesTrabajo as atendida' => fn (Builder $orden) => $orden->where(fn (Builder $servicio) => $servicio
                ->whereHas('mantenimientos', fn (Builder $q) => $q->where('estado_mantenimiento', 'FINALIZADO'))
                ->orWhereHas('calibraciones', fn (Builder $q) => $q->whereIn('estado_calibracion', self::CALIBRACION_TERMINADA))
                ->orWhereHas('serviciosTerceros', fn (Builder $q) => $q->where('estado_servicio', 'FINALIZADO')))])
            ->with(['equipo:id,codigo,modelo,area_id,tipo_equipo_id', 'equipo.area:id,nombre', 'equipo.tipoEquipo:id,nombre'])
            ->orderBy('fecha_proximo_servicio')
            ->get();

        // Un equipo por fila: atendido si se atendió alguna de sus programaciones del rango.
        $equipos = $programaciones
            ->groupBy('equipo_id')
            ->map(fn (Collection $deUnEquipo): array => [
                'equipo' => $deUnEquipo->first()->equipo,
                'fecha_programada' => $deUnEquipo->first()->fecha_proximo_servicio->toDateString(),
                'estado_programacion' => $deUnEquipo->first()->estado_programacion,
                'atendido' => $deUnEquipo->contains(fn (EquipoProgramacion $programacion) => (bool) $programacion->getAttribute('atendida')),
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
        ];
    }

    private function porcentaje(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte * 100 / $total, 1);
    }
}
