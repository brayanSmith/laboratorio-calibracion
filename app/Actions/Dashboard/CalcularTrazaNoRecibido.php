<?php

namespace App\Actions\Dashboard;

use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Equipos\Models\SeguimientoProgramacionEquipo;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Traza de un equipo que no llegó a un ingreso: su programación (con el ingreso, la
 * novedad y, si se re-agendó, para cuándo), el seguimiento de esa programación y lo que
 * pasó después con el equipo, en los ingresos siguientes y sus servicios.
 *
 * Re-agendar deja una programación nueva enlazada (ver
 * EquipoProgramacion::sincronizarReAgendamiento()), que a su vez puede no llegar y
 * re-agendarse: la traza sigue toda esa cadena, y el re-agendamiento se cumplió si el
 * último eslabón se recibió. Los re-agendados de antes de ese enlace se deducen: cumplido
 * si el equipo se recibió en un ingreso posterior. Si no, vencido si ya pasó la fecha y
 * pendiente si aún no llega.
 */
class CalcularTrazaNoRecibido
{
    /** Tope de eslabones que se siguen, por si un enlace quedara en ciclo. */
    private const MAX_ESLABONES = 50;

    /** Relaciones para mostrar una programación: su ingreso, su novedad y sus servicios. */
    private const RELACIONES = [
        'ingreso:id,bahia_id,desde,hasta,estado_ingreso,fecha_recepcion',
        'ingreso.bahia:id,nombre',
        'novedadIngreso:id,nombre',
        'ordenesTrabajo:id,equipo_programacion_id',
        'ordenesTrabajo.mantenimientos:id,orden_trabajo_id,estado_mantenimiento,fecha_mantenimiento,fecha_realizacion',
        'ordenesTrabajo.calibraciones:id,orden_trabajo_id,estado_calibracion,fecha_calibracion,fecha_realizacion',
        'ordenesTrabajo.serviciosTerceros:id,orden_trabajo_id,tipo_servicio,estado_servicio,fecha_realizacion',
        'ordenesTrabajo.despacho:id,orden_trabajo_id,estado_despacho,fecha_despacho',
    ];

    /**
     * @return array{programacion: array<string, mixed>, re_agendamiento: array<string, mixed>|null, seguimientos: list<array<string, mixed>>, posteriores: list<array<string, mixed>>}
     */
    public function handle(EquipoProgramacion $programacion): array
    {
        $programacion->loadMissing([
            'equipo:id,codigo,modelo,criticidad',
            'ingreso:id,bahia_id,desde,hasta,estado_ingreso,fecha_recepcion,motivo_cancelacion',
            'ingreso.bahia:id,nombre',
            'novedadIngreso:id,nombre',
            'seguimientos' => fn ($seguimientos) => $seguimientos->with('usuario:id,name')->orderBy('id'),
        ]);

        $cadena = $this->cadena($programacion);
        // Los eslabones de la cadena ya se muestran en el re-agendamiento.
        $posteriores = $this->posteriores($programacion, $cadena->pluck('id')->all());

        return [
            'programacion' => [
                'id' => $programacion->id,
                'equipo_id' => $programacion->equipo->id,
                'equipo' => $programacion->equipo->codigo,
                'modelo' => $programacion->equipo->modelo,
                'criticidad' => $programacion->equipo->criticidad,
                'tipo_servicio' => $programacion->tipo_servicio,
                'fecha_programada' => $programacion->fecha_proximo_servicio?->toDateString(),
                'estado_programacion' => $programacion->estado_programacion,
                'novedad' => $programacion->novedadIngreso?->nombre,
                'observacion' => $programacion->observacion_no_ingreso,
                'ingreso' => $programacion->ingreso ? [
                    ...$this->ingresoDe($programacion),
                    'motivo_cancelacion' => $programacion->ingreso->motivo_cancelacion,
                ] : null,
            ],
            're_agendamiento' => $programacion->re_agendar
                ? $this->reAgendamiento($programacion, $cadena, $posteriores)
                : null,
            'seguimientos' => $programacion->seguimientos
                ->map(fn (SeguimientoProgramacionEquipo $seguimiento): array => [
                    'id' => $seguimiento->id,
                    'fase' => $seguimiento->fase,
                    'seguimiento' => $seguimiento->seguimiento,
                    'observaciones' => $seguimiento->observaciones,
                    'usuario' => $seguimiento->usuario?->name,
                    'fecha' => $seguimiento->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'posteriores' => $posteriores->all(),
        ];
    }

    /**
     * Las programaciones que fue dejando cada re-agendamiento, desde la primera: la que
     * salió de esta, la que salió de esa (si tampoco llegó y se re-agendó), y así.
     *
     * @return Collection<int, EquipoProgramacion>
     */
    private function cadena(EquipoProgramacion $programacion): Collection
    {
        $cadena = collect();
        $actual = $programacion;

        while ($cadena->count() < self::MAX_ESLABONES) {
            $siguiente = EquipoProgramacion::query()
                ->where('re_agendada_de_id', $actual->id)
                ->with(self::RELACIONES)
                ->first();

            if (! $siguiente || $cadena->contains('id', $siguiente->id)) {
                break;
            }

            $cadena->push($siguiente);
            $actual = $siguiente;
        }

        return $cadena;
    }

    /**
     * Las otras programaciones del equipo en ingresos que empiezan desde el de esta (sin
     * contarla, ni a las de su cadena de re-agendamientos), en orden, cada una con su
     * ingreso y los servicios de sus órdenes.
     *
     * @param  list<int>  $excluir
     * @return Collection<int, array<string, mixed>>
     */
    private function posteriores(EquipoProgramacion $programacion, array $excluir): Collection
    {
        $desde = $programacion->ingreso?->desde;

        return EquipoProgramacion::query()
            ->where('tenant_id', $programacion->tenant_id)
            ->where('equipo_id', $programacion->equipo_id)
            ->whereKeyNot([$programacion->id, ...$excluir])
            ->whereHas('ingreso', fn (Builder $ingreso) => $ingreso
                ->when($desde, fn (Builder $query) => $query->whereDate('desde', '>=', $desde)))
            ->with(self::RELACIONES)
            ->get()
            ->sortBy([
                fn (EquipoProgramacion $a, EquipoProgramacion $b): int => $a->ingreso->desde <=> $b->ingreso->desde,
                fn (EquipoProgramacion $a, EquipoProgramacion $b): int => $a->id <=> $b->id,
            ])
            ->toBase()
            ->map(fn (EquipoProgramacion $siguiente): array => [
                'id' => $siguiente->id,
                'estado_programacion' => $siguiente->estado_programacion,
                'ingresado' => $this->llego($siguiente),
                'novedad' => $siguiente->novedadIngreso?->nombre,
                'ingreso' => $this->ingresoDe($siguiente),
                'servicios' => $this->serviciosDeLaProgramacion($siguiente),
            ])
            ->values();
    }

    /**
     * El re-agendamiento con toda su cadena: un eslabón por cada vez que se re-agendó (la
     * fecha para la que quedó, en qué ingreso entró y si llegó). Se cumplió si el último
     * eslabón se recibió; si no, vencido si su fecha ya pasó y pendiente si aún no llega.
     *
     * @param  Collection<int, EquipoProgramacion>  $cadena
     * @param  Collection<int, array<string, mixed>>  $posteriores
     * @return array{fecha: string|null, estado: string, veces: int, cadena: list<array<string, mixed>>}
     */
    private function reAgendamiento(EquipoProgramacion $programacion, Collection $cadena, Collection $posteriores): array
    {
        $ultimo = $cadena->last();
        // Sin cadena (re-agendados de antes del enlace): la fecha guardada y lo posterior.
        $fecha = $ultimo
            ? $ultimo->fecha_proximo_servicio?->toDateString()
            : ($programacion->datos_re_agendamiento['fecha_proximo_agendamiento'] ?? null);
        $recibido = $ultimo
            ? $this->llego($ultimo)
            : $posteriores->contains(fn (array $siguiente): bool => $siguiente['ingresado']);

        return [
            'fecha' => $fecha,
            'estado' => match (true) {
                $recibido => 'CUMPLIDO',
                $fecha !== null && $fecha < now()->toDateString() => 'VENCIDO',
                default => 'PENDIENTE',
            },
            'veces' => max(1, $cadena->count()),
            'cadena' => $cadena
                ->map(fn (EquipoProgramacion $eslabon): array => [
                    'id' => $eslabon->id,
                    // La fecha que se eligió al re-agendar (la programada de este eslabón).
                    'fecha' => $eslabon->fecha_proximo_servicio?->toDateString(),
                    'estado_programacion' => $eslabon->estado_programacion,
                    'ingresado' => $this->llego($eslabon),
                    'novedad' => $eslabon->novedadIngreso?->nombre,
                    'observacion' => $eslabon->observacion_no_ingreso,
                    'ingreso' => $eslabon->ingreso ? $this->ingresoDe($eslabon) : null,
                    'servicios' => $this->serviciosDeLaProgramacion($eslabon),
                ])
                ->values()
                ->all(),
        ];
    }

    /** Si el equipo llegó con esta programación: recibida en un ingreso recibido. */
    private function llego(EquipoProgramacion $programacion): bool
    {
        return $programacion->estado_programacion === 'RECIBIDO'
            || ($programacion->ingresado && $programacion->ingreso?->estado_ingreso === 'RECIBIDO');
    }

    /**
     * @return array{id: int, bahia: string|null, desde: string, hasta: string, estado_ingreso: string, fecha_recepcion: string|null}
     */
    private function ingresoDe(EquipoProgramacion $programacion): array
    {
        return [
            'id' => $programacion->ingreso->id,
            'bahia' => $programacion->ingreso->bahia?->nombre,
            'desde' => $programacion->ingreso->desde->toDateString(),
            'hasta' => $programacion->ingreso->hasta->toDateString(),
            'estado_ingreso' => $programacion->ingreso->estado_ingreso,
            'fecha_recepcion' => $programacion->ingreso->fecha_recepcion?->toDateString(),
        ];
    }

    /**
     * @return list<array{servicio: string, estado: string, fecha: string|null, tercero: bool}>
     */
    private function serviciosDeLaProgramacion(EquipoProgramacion $programacion): array
    {
        return $programacion->ordenesTrabajo
            ->flatMap(fn (OrdenTrabajo $orden): array => $this->serviciosDe($orden))
            ->values()
            ->all();
    }

    /**
     * Los servicios de una orden de trabajo, en el orden en que ocurren: mantenimiento,
     * calibración (propios o de terceros) y despacho.
     *
     * @return list<array{servicio: string, estado: string, fecha: string|null, tercero: bool}>
     */
    private function serviciosDe(OrdenTrabajo $orden): array
    {
        return [
            ...$orden->mantenimientos->map(fn (Mantenimiento $mantenimiento): array => [
                'servicio' => 'MANTENIMIENTO',
                'estado' => $mantenimiento->estado_mantenimiento,
                'fecha' => ($mantenimiento->fecha_realizacion ?? $mantenimiento->fecha_mantenimiento)?->toDateString(),
                'tercero' => false,
            ])->all(),
            ...$orden->serviciosTerceros->where('tipo_servicio', 'MANTENIMIENTO')->map(fn (ServicioTercero $servicio): array => $this->deTercero($servicio))->all(),
            ...$orden->calibraciones->map(fn (Calibracion $calibracion): array => [
                'servicio' => 'CALIBRACION',
                'estado' => $calibracion->estado_calibracion,
                'fecha' => ($calibracion->fecha_realizacion ?? $calibracion->fecha_calibracion)?->toDateString(),
                'tercero' => false,
            ])->all(),
            ...$orden->serviciosTerceros->where('tipo_servicio', 'CALIBRACION')->map(fn (ServicioTercero $servicio): array => $this->deTercero($servicio))->all(),
            ...($orden->despacho ? [[
                'servicio' => 'DESPACHO',
                'estado' => $orden->despacho->estado_despacho,
                'fecha' => $orden->despacho->fecha_despacho?->toDateString(),
                'tercero' => false,
            ]] : []),
        ];
    }

    /**
     * @return array{servicio: string, estado: string, fecha: string|null, tercero: bool}
     */
    private function deTercero(ServicioTercero $servicio): array
    {
        return [
            'servicio' => $servicio->tipo_servicio,
            'estado' => $servicio->estado_servicio,
            'fecha' => $servicio->fecha_realizacion?->toDateString(),
            'tercero' => true,
        ];
    }
}
