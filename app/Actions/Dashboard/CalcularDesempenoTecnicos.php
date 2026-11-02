<?php

namespace App\Actions\Dashboard;

use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cuántos mantenimientos y calibraciones terminó cada técnico (propio) o empresa tercera
 * en el rango, y cuánto tardaron en promedio. La duración es la de su tiempo de servicio
 * (de "Iniciar" a "Finalizar"); los servicios sin tiempo registrado cuentan en la cantidad
 * pero no en el promedio.
 */
class CalcularDesempenoTecnicos
{
    /** Estados con los que una calibración queda terminada. */
    private const CALIBRACION_TERMINADA = ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

    /**
     * @param  array{desde: string, hasta: string, laboratorio?: string|int|null, tecnico_id?: int|null, tipo_equipo_id?: int|null}  $filtros
     * @return array{responsables: list<array<string, mixed>>, totales: array<string, mixed>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        $laboratorio = $filtros['laboratorio'] ?? null;
        $tecnicoId = $filtros['tecnico_id'] ?? null;
        $incluirPropios = $laboratorio === null || $laboratorio === 'propio';
        // Al filtrar por un técnico solo se ven sus servicios, que son propios.
        $incluirTerceros = $tecnicoId === null && $laboratorio !== 'propio';

        $servicios = collect();

        if ($incluirPropios) {
            $servicios = $servicios
                ->concat($this->propios(Mantenimiento::query()->where('estado_mantenimiento', 'FINALIZADO'), 'MANTENIMIENTO', $tenantId, $filtros))
                ->concat($this->propios(Calibracion::query()->whereIn('estado_calibracion', self::CALIBRACION_TERMINADA), 'CALIBRACION', $tenantId, $filtros));
        }

        if ($incluirTerceros) {
            $servicios = $servicios->concat($this->terceros($tenantId, $filtros));
        }

        $duraciones = $this->duraciones($servicios->pluck('orden_trabajo_id')->unique()->values()->all());

        $servicios = $servicios->map(fn (array $servicio): array => [
            ...$servicio,
            'segundos' => $duraciones->get("{$servicio['orden_trabajo_id']}|{$servicio['tipo_servicio']}|".($servicio['tipo'] === 'TERCERO' ? 1 : 0)),
        ]);

        $responsables = $servicios
            ->groupBy(fn (array $servicio): string => "{$servicio['tipo']}|{$servicio['responsable_id']}")
            ->map(fn (Collection $deUnResponsable): array => [
                'tipo' => $deUnResponsable->first()['tipo'],
                'responsable_id' => $deUnResponsable->first()['responsable_id'],
                'responsable' => $deUnResponsable->first()['responsable'],
                'mantenimientos' => $this->resumen($deUnResponsable->where('tipo_servicio', 'MANTENIMIENTO')),
                'calibraciones' => $this->resumen($deUnResponsable->where('tipo_servicio', 'CALIBRACION')),
            ])
            ->sortByDesc(fn (array $fila): int => $fila['mantenimientos']['cantidad'] + $fila['calibraciones']['cantidad'])
            ->values();

        return [
            'responsables' => $responsables->all(),
            'totales' => [
                'mantenimientos' => $this->resumen($servicios->where('tipo_servicio', 'MANTENIMIENTO')),
                'calibraciones' => $this->resumen($servicios->where('tipo_servicio', 'CALIBRACION')),
            ],
        ];
    }

    /**
     * Mantenimientos o calibraciones propios terminados en el rango, con su técnico.
     *
     * @param  Builder<Mantenimiento>|Builder<Calibracion>  $query
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    private function propios(Builder $query, string $tipoServicio, int $tenantId, array $filtros): Collection
    {
        return $query
            ->where('tenant_id', $tenantId)
            ->whereNotNull('tecnico_id')
            ->whereDate('fecha_realizacion', '>=', $filtros['desde'])
            ->whereDate('fecha_realizacion', '<=', $filtros['hasta'])
            ->when($filtros['tecnico_id'] ?? null, fn (Builder $q, int $tecnicoId) => $q->where('tecnico_id', $tecnicoId))
            ->when($filtros['tipo_equipo_id'] ?? null, fn (Builder $q, int $tipoEquipoId) => $q->whereHas(
                'ordenTrabajo.equipoProgramacion.equipo',
                fn (Builder $equipo) => $equipo->where('tipo_equipo_id', $tipoEquipoId),
            ))
            ->with('tecnico:id,name')
            ->get(['id', 'orden_trabajo_id', 'tecnico_id'])
            ->map(fn (Mantenimiento|Calibracion $servicio): array => [
                'tipo' => 'TECNICO',
                'responsable_id' => $servicio->tecnico_id,
                'responsable' => $servicio->tecnico?->name ?? 'Sin técnico',
                'tipo_servicio' => $tipoServicio,
                'orden_trabajo_id' => $servicio->orden_trabajo_id,
            ]);
    }

    /**
     * Servicios de terceros terminados en el rango, con su empresa.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    private function terceros(int $tenantId, array $filtros): Collection
    {
        $laboratorio = $filtros['laboratorio'] ?? null;

        return ServicioTercero::query()
            ->where('tenant_id', $tenantId)
            ->where('estado_servicio', 'FINALIZADO')
            ->whereDate('fecha_realizacion', '>=', $filtros['desde'])
            ->whereDate('fecha_realizacion', '<=', $filtros['hasta'])
            ->when($laboratorio !== null, fn (Builder $q) => $q->where('empresa_tercero_id', $laboratorio))
            ->when($filtros['tipo_equipo_id'] ?? null, fn (Builder $q, int $tipoEquipoId) => $q->whereHas(
                'ordenTrabajo.equipoProgramacion.equipo',
                fn (Builder $equipo) => $equipo->where('tipo_equipo_id', $tipoEquipoId),
            ))
            ->with('empresaTercero:id,nombre')
            ->get(['id', 'orden_trabajo_id', 'empresa_tercero_id', 'tipo_servicio'])
            ->map(fn (ServicioTercero $servicio): array => [
                'tipo' => 'TERCERO',
                'responsable_id' => $servicio->empresa_tercero_id,
                'responsable' => $servicio->empresaTercero?->nombre ?? 'Sin empresa',
                'tipo_servicio' => $servicio->tipo_servicio,
                'orden_trabajo_id' => $servicio->orden_trabajo_id,
            ]);
    }

    /**
     * Segundos de servicio terminados por "orden|tipo de servicio|es tercero" (si una orden
     * tuvo varios tramos del mismo servicio, se suman).
     *
     * @param  list<int>  $ordenTrabajoIds
     * @return Collection<string, int>
     */
    private function duraciones(array $ordenTrabajoIds): Collection
    {
        return TiempoServicio::query()
            ->whereIn('orden_trabajo_id', $ordenTrabajoIds)
            ->whereNotNull('fin')
            ->get(['orden_trabajo_id', 'tipo_servicio', 'es_tercero', 'inicio', 'fin'])
            ->groupBy(fn (TiempoServicio $tiempo): string => "{$tiempo->orden_trabajo_id}|{$tiempo->tipo_servicio}|".($tiempo->es_tercero ? 1 : 0))
            ->map(fn (Collection $tramos): int => (int) $tramos->sum(fn (TiempoServicio $tiempo) => $tiempo->inicio->diffInSeconds($tiempo->fin)));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $servicios
     * @return array{cantidad: int, promedio_horas: float|null}
     */
    private function resumen(Collection $servicios): array
    {
        $conTiempo = $servicios->pluck('segundos')->filter(fn (?int $segundos) => $segundos !== null);

        return [
            'cantidad' => $servicios->count(),
            'promedio_horas' => $conTiempo->isEmpty() ? null : round($conTiempo->avg() / 3600, 2),
        ];
    }
}
