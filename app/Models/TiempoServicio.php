<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orden_trabajo_id
 * @property string $tipo_servicio
 * @property Carbon $inicio
 * @property Carbon|null $fin Nulo mientras el servicio sigue en curso
 * @property string|null $duracion Nulo mientras el servicio sigue en curso
 * @property string $estado_tiempo
 * @property bool $es_tercero
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read Tenant $tenant
 */
#[Fillable(['orden_trabajo_id', 'tipo_servicio', 'inicio', 'fin', 'duracion', 'estado_tiempo', 'es_tercero', 'tenant_id'])]
class TiempoServicio extends Model
{
    use SoftDeletes;

    /**
     * Suma duraciones con formato HH:MM:SS (las de varios tiempos de servicio de una misma
     * orden, por ejemplo tras una pausa) y devuelve el total con el mismo formato, o null si
     * no hay ninguna.
     *
     * @param  iterable<int, string|null>  $duraciones
     */
    public static function sumarDuraciones(iterable $duraciones): ?string
    {
        $segundos = null;

        foreach ($duraciones as $duracion) {
            if (! is_string($duracion) || ! preg_match('/^(\d+):(\d{2}):(\d{2})$/', $duracion, $partes)) {
                continue;
            }

            $segundos = ($segundos ?? 0) + (int) $partes[1] * 3600 + (int) $partes[2] * 60 + (int) $partes[3];
        }

        return $segundos === null
            ? null
            : sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
    }

    /**
     * Texto corto y legible de una duración HH:MM:SS: sin segundos y con las dos unidades
     * mayores ("1d 4h", "2h 16m", "45m"); "<1m" si dura menos de un minuto y null si no hay.
     */
    public static function formatearDuracion(?string $duracion): ?string
    {
        if ($duracion === null || ! preg_match('/^(\d+):(\d{2}):(\d{2})$/', $duracion, $partes)) {
            return null;
        }

        $minutosTotales = (int) $partes[1] * 60 + (int) $partes[2];
        $dias = intdiv($minutosTotales, 1440);
        $horas = intdiv($minutosTotales % 1440, 60);
        $minutos = $minutosTotales % 60;

        return match (true) {
            $dias > 0 => "{$dias}d {$horas}h",
            $horas > 0 => "{$horas}h {$minutos}m",
            $minutos > 0 => "{$minutos}m",
            default => '<1m',
        };
    }

    /**
     * Duración total de los tiempos de servicio ya cerrados de mantenimiento propio de una
     * orden de trabajo, o null si ninguno terminó todavía.
     */
    public static function duracionMantenimiento(int $ordenTrabajoId): ?string
    {
        return self::sumarDuraciones(
            self::query()
                ->where('orden_trabajo_id', $ordenTrabajoId)
                ->where('tipo_servicio', 'MANTENIMIENTO')
                ->where('es_tercero', false)
                ->where('estado_tiempo', 'FIN')
                ->pluck('duracion'),
        );
    }

    /**
     * Get the orden de trabajo this tiempo de servicio belongs to.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * Get the tenant this tiempo de servicio belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'es_tercero' => 'boolean',
        ];
    }
}
