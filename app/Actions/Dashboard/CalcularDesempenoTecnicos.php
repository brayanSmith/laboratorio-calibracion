<?php

namespace App\Actions\Dashboard;

use App\Concerns\SerieMensual;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cuántos mantenimientos y calibraciones terminó cada técnico (propio) o empresa tercera
 * en el rango, y cuánto tardaron en promedio. La duración es la de su tiempo de servicio
 * (de "Iniciar" a "Finalizar"); los servicios sin tiempo registrado cuentan en la cantidad
 * pero no en el promedio.
 *
 * Los filtros son listas dependientes de selección múltiple (ver FiltrosDependientes), en
 * este orden: laboratorio → técnico → magnitud → tipo de equipo. Los servicios de terceros
 * no tienen técnico: al elegir técnicos solo quedan los propios.
 */
class CalcularDesempenoTecnicos
{
    use SerieMensual;

    /** Estados con los que una calibración queda terminada. */
    private const CALIBRACION_TERMINADA = ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

    /** Tipos de servicio que se pueden ver: mantenimientos y calibraciones. */
    public const TIPOS_SERVICIO = ['MANTENIMIENTO', 'CALIBRACION'];

    /** Relaciones del equipo del servicio que usan los filtros. */
    private const EQUIPO = [
        'ordenTrabajo:id,equipo_programacion_id',
        'ordenTrabajo.equipoProgramacion:id,equipo_id',
        'ordenTrabajo.equipoProgramacion.equipo:id,tipo_equipo_id',
        'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
        'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica:id,equipo_id,tipo_magnitud_id',
        'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica.tipoMagnitud:id,nombre',
    ];

    /**
     * @param  array{desde: string, hasta: string, laboratorios?: list<int|string>, tecnicos?: list<int>, magnitudes?: list<int>, tipos_equipo?: list<int>, servicios?: list<string>}  $filtros
     * @return array{responsables: list<array<string, mixed>>, totales: array<string, mixed>, mensual: list<array<string, mixed>>, servicios: list<array{fecha: string, tipo_servicio: string, tipo_equipo_id: int|null, horas: float|null}>, opciones: array<string, list<array{id: int|string, nombre: string}>>}
     */
    public function handle(int $tenantId, array $filtros): array
    {
        // Mantenimientos, calibraciones o los dos (sin elegir, los dos).
        $tiposServicio = ($filtros['servicios'] ?? []) ?: self::TIPOS_SERVICIO;

        $delPeriodo = collect();

        if (in_array('MANTENIMIENTO', $tiposServicio, true)) {
            $delPeriodo = $delPeriodo->concat($this->propios(Mantenimiento::query()->where('estado_mantenimiento', 'FINALIZADO'), 'MANTENIMIENTO', $tenantId, $filtros));
        }

        if (in_array('CALIBRACION', $tiposServicio, true)) {
            $delPeriodo = $delPeriodo->concat($this->propios(Calibracion::query()->whereIn('estado_calibracion', self::CALIBRACION_TERMINADA), 'CALIBRACION', $tenantId, $filtros));
        }

        $delPeriodo = $delPeriodo->concat($this->terceros($tenantId, $filtros, $tiposServicio));

        ['filas' => $servicios, 'opciones' => $opciones] = FiltrosDependientes::aplicar($delPeriodo, [
            'laboratorios' => $filtros['laboratorios'] ?? [],
            'tecnicos' => $filtros['tecnicos'] ?? [],
            'magnitudes' => $filtros['magnitudes'] ?? [],
            'tipos_equipo' => $filtros['tipos_equipo'] ?? [],
        ]);

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
            // Por mes de realización: cantidad y tiempo promedio (para los gráficos con el objetivo).
            'mensual' => $this->serieMensual(
                $filtros['desde'],
                $filtros['hasta'],
                $servicios,
                fn (array $servicio): string => $servicio['mes'],
                fn (Collection $delMes): array => [
                    'mantenimientos' => $this->resumen($delMes->where('tipo_servicio', 'MANTENIMIENTO')),
                    'calibraciones' => $this->resumen($delMes->where('tipo_servicio', 'CALIBRACION')),
                ],
            ),
            'opciones' => $opciones,
            // Cada servicio con su día y sus horas (null sin tiempo registrado), para que el
            // gráfico los agrupe por días, semanas, meses... sin volver a pedirlos.
            'servicios' => $servicios
                ->map(fn (array $servicio): array => [
                    'fecha' => $servicio['fecha'],
                    'tipo_servicio' => $servicio['tipo_servicio'],
                    'tipo_equipo_id' => $servicio['filtros']['tipos_equipo'][0]['id'] ?? null,
                    'horas' => $servicio['segundos'] === null ? null : round($servicio['segundos'] / 3600, 2),
                ])
                ->values()
                ->all(),
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
            ->with(['tecnico:id,name', ...self::EQUIPO])
            ->get(['id', 'orden_trabajo_id', 'tecnico_id', 'fecha_realizacion'])
            ->map(fn (Mantenimiento|Calibracion $servicio): array => [
                'tipo' => 'TECNICO',
                'responsable_id' => $servicio->tecnico_id,
                'responsable' => $servicio->tecnico?->name ?? 'Sin técnico',
                'tipo_servicio' => $tipoServicio,
                'orden_trabajo_id' => $servicio->orden_trabajo_id,
                'mes' => $servicio->fecha_realizacion->format('Y-m'),
                'fecha' => $servicio->fecha_realizacion->toDateString(),
                'filtros' => [
                    'laboratorios' => [['id' => FiltrosDependientes::PROPIO, 'nombre' => 'Laboratorio propio']],
                    'tecnicos' => FiltrosDependientes::opcion($servicio->tecnico, 'name'),
                    ...$this->filtrosDelEquipo($servicio->ordenTrabajo),
                ],
            ]);
    }

    /**
     * Servicios de terceros terminados en el rango, con su empresa (no tienen técnico).
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $tiposServicio
     * @return Collection<int, array<string, mixed>>
     */
    private function terceros(int $tenantId, array $filtros, array $tiposServicio): Collection
    {
        return ServicioTercero::query()
            ->where('tenant_id', $tenantId)
            ->where('estado_servicio', 'FINALIZADO')
            ->whereIn('tipo_servicio', $tiposServicio)
            ->whereDate('fecha_realizacion', '>=', $filtros['desde'])
            ->whereDate('fecha_realizacion', '<=', $filtros['hasta'])
            ->with(['empresaTercero:id,nombre', ...self::EQUIPO])
            ->get(['id', 'orden_trabajo_id', 'empresa_tercero_id', 'tipo_servicio', 'fecha_realizacion'])
            ->map(fn (ServicioTercero $servicio): array => [
                'tipo' => 'TERCERO',
                'responsable_id' => $servicio->empresa_tercero_id,
                'responsable' => $servicio->empresaTercero?->nombre ?? 'Sin empresa',
                'tipo_servicio' => $servicio->tipo_servicio,
                'orden_trabajo_id' => $servicio->orden_trabajo_id,
                'mes' => $servicio->fecha_realizacion->format('Y-m'),
                'fecha' => $servicio->fecha_realizacion->toDateString(),
                'filtros' => [
                    'laboratorios' => FiltrosDependientes::opcion($servicio->empresaTercero),
                    'tecnicos' => [],
                    ...$this->filtrosDelEquipo($servicio->ordenTrabajo),
                ],
            ]);
    }

    /**
     * Magnitud y tipo del equipo de la orden, para los filtros.
     *
     * @return array{magnitudes: list<array{id: int|string, nombre: string}>, tipos_equipo: list<array{id: int|string, nombre: string}>}
     */
    private function filtrosDelEquipo(?OrdenTrabajo $ordenTrabajo): array
    {
        /** @var Equipo|null $equipo */
        $equipo = $ordenTrabajo?->equipoProgramacion?->equipo;

        return [
            'magnitudes' => FiltrosDependientes::opcion($equipo?->equipoEspecificacionTecnica?->tipoMagnitud),
            'tipos_equipo' => FiltrosDependientes::opcion($equipo?->tipoEquipo),
        ];
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
