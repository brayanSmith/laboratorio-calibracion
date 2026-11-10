<?php

namespace App\Actions\Dashboard;

use App\Concerns\SerieMensual;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
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

    /** Etapas en que se desglosan los atendidos y los pendientes. */
    private const SERVICIOS = ['MANTENIMIENTO', 'CALIBRACION'];

    /**
     * @param  array{desde: string, hasta: string, areas?: list<int>, magnitudes?: list<int>, tipos_equipo?: list<int>, tipos_mantenimiento?: list<string>}  $filtros
     * @return array{resumen: array{programados: int, atendidos: int, fuera_de_servicio: int, fuera_de_servicio_por_servicio: array<string, int>, pendientes: int, porcentaje: float|null, por_servicio: array<string, array{atendidos: int, pendientes: int}>}, por_area: list<array<string, mixed>>, mensual: list<array<string, mixed>>, por_fecha: list<array{fecha: string, atendido: bool}>, pendientes: list<array<string, mixed>>, opciones: array<string, list<array{id: int|string, nombre: string}>>}
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
            // La orden ya pasó a calibración (propia o de tercero, en cualquier estado): el
            // mantenimiento de la misma orden quedó atrás.
            ->withExists(['ordenesTrabajo as paso_a_calibracion' => fn (Builder $orden) => $orden->where(fn (Builder $servicio) => $servicio
                ->whereHas('calibraciones')
                ->orWhereHas('serviciosTerceros', fn (Builder $q) => $q->where('tipo_servicio', 'CALIBRACION')))])
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
                    'etapa' => $this->etapaDelEquipo($deUnEquipo),
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
        $fueraDeServicio = $this->fueraDeServicio($tenantId, $filtros, $tiposMantenimiento);

        return [
            'resumen' => [
                'programados' => $equipos->count(),
                'atendidos' => $atendidos,
                // Equipos que quedaron fuera de servicio, por la fecha del servicio (no la programada).
                'fuera_de_servicio' => array_sum($fueraDeServicio),
                'fuera_de_servicio_por_servicio' => $fueraDeServicio,
                'pendientes' => $equipos->count() - $atendidos,
                'porcentaje' => $this->porcentaje($atendidos, $equipos->count()),
                // Por la etapa en que va cada equipo: cuenta una sola vez, así que suman los totales.
                'por_servicio' => collect(self::SERVICIOS)
                    ->mapWithKeys(function (string $etapa) use ($equipos): array {
                        $enLaEtapa = $equipos->where('etapa', $etapa);

                        return [$etapa => [
                            'atendidos' => $enLaEtapa->where('atendido', true)->count(),
                            'pendientes' => $enLaEtapa->where('atendido', false)->count(),
                        ]];
                    })
                    ->all(),
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
            // Cada equipo con su fecha programada y si se atendió, para que el gráfico los
            // agrupe por días, semanas, meses... sin volver a pedirlos.
            'por_fecha' => $equipos
                ->map(fn (array $fila): array => [
                    'fecha' => $fila['fecha_programada'],
                    'atendido' => $fila['atendido'],
                ])
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
            'opciones' => $opciones,
        ];
    }

    /**
     * En qué servicio va el equipo: el mantenimiento y la calibración van en la misma orden
     * (primero el mantenimiento), así que está en calibración si su orden ya pasó a
     * calibración o si solo tiene calibración programada; si no, en mantenimiento. El tipo
     * de servicio de una programación es una lista separada por comas (ej.
     * "MANTENIMIENTO,CALIBRACION").
     *
     * @param  Collection<int, EquipoProgramacion>  $programaciones
     */
    private function etapaDelEquipo(Collection $programaciones): string
    {
        if ($programaciones->contains(fn (EquipoProgramacion $programacion): bool => (bool) $programacion->getAttribute('paso_a_calibracion'))) {
            return 'CALIBRACION';
        }

        $conMantenimiento = $programaciones->contains(
            fn (EquipoProgramacion $programacion): bool => in_array('MANTENIMIENTO', array_map('trim', explode(',', $programacion->tipo_servicio)), true),
        );

        return $conMantenimiento ? 'MANTENIMIENTO' : 'CALIBRACION';
    }

    /**
     * Equipos que quedaron fuera de servicio en el rango, por servicio y según la fecha del
     * servicio: la del mantenimiento, la de la calibración o, en los de terceros, la de
     * realización. Con los mismos filtros del indicador; cada equipo cuenta una vez (si fue
     * en los dos, en la calibración, el último paso de la orden).
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $tiposMantenimiento
     * @return array{MANTENIMIENTO: int, CALIBRACION: int}
     */
    private function fueraDeServicio(int $tenantId, array $filtros, array $tiposMantenimiento): array
    {
        $delEquipo = function (Builder $servicio) use ($filtros, $tiposMantenimiento): void {
            $servicio->whereHas('ordenTrabajo.equipoProgramacion.equipo', fn (Builder $equipo) => $equipo
                ->whereHas('tipoEquipo', fn (Builder $tipo) => $tipo->whereIn('tipo_mantenimiento', $tiposMantenimiento))
                ->when($filtros['areas'] ?? [], fn (Builder $query, array $areas) => $query->whereIn('area_id', $areas))
                ->when($filtros['tipos_equipo'] ?? [], fn (Builder $query, array $tipos) => $query->whereIn('tipo_equipo_id', $tipos))
                ->when($filtros['magnitudes'] ?? [], fn (Builder $query, array $magnitudes) => $query
                    ->whereHas('equipoEspecificacionTecnica', fn (Builder $especificacion) => $especificacion->whereIn('tipo_magnitud_id', $magnitudes))));
        };
        $equiposDe = fn (Builder $servicios): Collection => $servicios
            ->where('tenant_id', $tenantId)
            ->where($delEquipo)
            ->with('ordenTrabajo.equipoProgramacion:id,equipo_id')
            ->get()
            // A una colección común: vacía, el map seguiría siendo Eloquent y su diff()
            // trataría los ids como modelos.
            ->toBase()
            ->map(fn (Mantenimiento|Calibracion|ServicioTercero $servicio): ?int => $servicio->ordenTrabajo?->equipoProgramacion?->equipo_id)
            ->filter()
            ->unique();
        $terceros = fn (string $tipoServicio): Builder => ServicioTercero::query()
            ->where('tipo_servicio', $tipoServicio)
            ->where('estado_servicio', 'FINALIZADO')
            ->where('estado_final_equipo', 'FUERA_DE_SERVICIO')
            ->whereDate('fecha_realizacion', '>=', $filtros['desde'])
            ->whereDate('fecha_realizacion', '<=', $filtros['hasta']);

        $enCalibracion = $equiposDe(Calibracion::query()
            ->where('estado_calibracion', 'FUERA_DE_SERVICIO')
            ->whereDate('fecha_calibracion', '>=', $filtros['desde'])
            ->whereDate('fecha_calibracion', '<=', $filtros['hasta']))
            ->merge($equiposDe($terceros('CALIBRACION')))
            ->unique();
        $enMantenimiento = $equiposDe(Mantenimiento::query()
            ->where('estado_mantenimiento', 'FINALIZADO')
            ->where('estado_final_equipo', 'FUERA_DE_SERVICIO')
            ->whereDate('fecha_mantenimiento', '>=', $filtros['desde'])
            ->whereDate('fecha_mantenimiento', '<=', $filtros['hasta']))
            ->merge($equiposDe($terceros('MANTENIMIENTO')))
            ->unique()
            ->diff($enCalibracion);

        return [
            'MANTENIMIENTO' => $enMantenimiento->count(),
            'CALIBRACION' => $enCalibracion->count(),
        ];
    }

    private function porcentaje(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte * 100 / $total, 1);
    }
}
