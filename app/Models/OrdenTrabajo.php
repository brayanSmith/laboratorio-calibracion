<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int|null $despacho_id
 * @property int $equipo_programacion_id
 * @property Carbon $fecha_programada_orden_trabajo
 * @property string $estado
 * @property bool $listo_para_mantenimiento Marcado desde "Agendar Mantenimiento"
 * @property bool $mantenimiento_asignado_tercero
 * @property bool $mantenimiento_finalizado Se marca al finalizar el mantenimiento
 * @property bool $listo_para_calibracion
 * @property bool $calibracion_asignado_tercero
 * @property bool $calibracion_finalizado Se marca al finalizar la calibración
 * @property bool $orden_trabajo_programada
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Despacho|null $despacho
 * @property-read EquipoProgramacion $equipoProgramacion
 * @property-read Tenant $tenant
 * @property-read string $estadoVencimiento Proviene de equipoProgramacion: no se duplica aquí
 * @property-read bool $requiereCalibracion Se deriva del tipo_servicio de la programación
 */
#[Fillable([
    'codigo', 'despacho_id', 'equipo_programacion_id',
    'fecha_programada_orden_trabajo', 'estado', 'listo_para_mantenimiento',
    'mantenimiento_asignado_tercero', 'mantenimiento_finalizado', 'listo_para_calibracion',
    'calibracion_asignado_tercero', 'calibracion_finalizado',
    'orden_trabajo_programada', 'tenant_id',
])]
class OrdenTrabajo extends Model
{
    use SoftDeletes;

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
     * Get the programacion de servicio that originated this orden de trabajo. El equipo,
     * el ingreso y si el equipo llegó o no se consultan a través de ella (ej.
     * $ordenTrabajo->equipoProgramacion->equipo, ->ingreso, o estado_programacion).
     *
     * @return BelongsTo<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): BelongsTo
    {
        return $this->belongsTo(EquipoProgramacion::class);
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
     * El vencimiento de esta orden es el mismo de la programación que la originó: no se
     * guarda por separado, se lee de ahí para que no se puedan desincronizar.
     *
     * @return Attribute<string, never>
     */
    protected function estadoVencimiento(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->equipoProgramacion->estado_vencimiento,
        );
    }

    /**
     * Si la programación que originó esta orden incluye CALIBRACION en su tipo_servicio
     * (CSV): no se guarda por separado, se lee de ahí para que no se puedan desincronizar.
     *
     * @return Attribute<bool, never>
     */
    protected function requiereCalibracion(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => in_array('CALIBRACION', explode(',', $this->equipoProgramacion->tipo_servicio), true),
        );
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
            'listo_para_mantenimiento' => 'boolean',
            'mantenimiento_asignado_tercero' => 'boolean',
            'mantenimiento_finalizado' => 'boolean',
            'listo_para_calibracion' => 'boolean',
            'calibracion_asignado_tercero' => 'boolean',
            'calibracion_finalizado' => 'boolean',
            'orden_trabajo_programada' => 'boolean',
        ];
    }
}
