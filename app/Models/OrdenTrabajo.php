<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int|null $ingreso_id
 * @property int|null $despacho_id
 * @property int $equipo_id
 * @property Carbon $fecha_programada_orden_trabajo
 * @property Carbon $fecha_vencimiento
 * @property string $dias_plazo_vencimiento
 * @property string $estado_vencimiento
 * @property string $estado
 * @property bool $equipo_ingresado
 * @property int $novedad_ingreso_id
 * @property bool $requiere_calibracion
 * @property bool $mantenimiento_asignado_tercero
 * @property bool $calibracion_asignado_tercero
 * @property bool $orden_trabajo_programada
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ingreso|null $ingreso
 * @property-read Despacho|null $despacho
 * @property-read Equipo $equipo
 * @property-read Novedad $novedadIngreso
 * @property-read Tenant $tenant
 */
#[Fillable([
    'codigo', 'ingreso_id', 'despacho_id', 'equipo_id', 'fecha_programada_orden_trabajo',
    'fecha_vencimiento', 'dias_plazo_vencimiento', 'estado_vencimiento', 'estado',
    'equipo_ingresado', 'novedad_ingreso_id', 'requiere_calibracion',
    'mantenimiento_asignado_tercero', 'calibracion_asignado_tercero',
    'orden_trabajo_programada', 'tenant_id',
])]
class OrdenTrabajo extends Model
{
    use SoftDeletes;

    /**
     * Get the ingreso that originated this orden de trabajo.
     *
     * @return BelongsTo<Ingreso, $this>
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    /**
     * Get the despacho that closed this orden de trabajo.
     *
     * @return BelongsTo<Despacho, $this>
     */
    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }

    /**
     * Get the equipo this orden de trabajo is for.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the novedad registered at ingreso.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedadIngreso(): BelongsTo
    {
        return $this->belongsTo(Novedad::class, 'novedad_ingreso_id');
    }

    /**
     * Get the tenant this orden de trabajo belongs to.
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
            'fecha_programada_orden_trabajo' => 'date',
            'fecha_vencimiento' => 'date',
            'dias_plazo_vencimiento' => 'decimal:2',
            'equipo_ingresado' => 'boolean',
            'requiere_calibracion' => 'boolean',
            'mantenimiento_asignado_tercero' => 'boolean',
            'calibracion_asignado_tercero' => 'boolean',
            'orden_trabajo_programada' => 'boolean',
        ];
    }
}
