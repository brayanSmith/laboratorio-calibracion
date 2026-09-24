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
 * @property int $laboratorio_id
 * @property int $solicitante_id
 * @property int $tecnico_id
 * @property string|null $temperatura
 * @property string|null $humedad
 * @property int $procedimiento_id
 * @property bool $ajustes_requeridos
 * @property string $estado_calibracion
 * @property bool $firmado
 * @property int $novedad_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read Laboratorio $laboratorio
 * @property-read Area $solicitante
 * @property-read User $tecnico
 * @property-read ProcedimientoCalibracion $procedimiento
 * @property-read Novedad $novedad
 * @property-read Tenant $tenant
 */
#[Fillable([
    'orden_trabajo_id', 'laboratorio_id', 'solicitante_id', 'tecnico_id', 'temperatura',
    'humedad', 'procedimiento_id', 'ajustes_requeridos', 'estado_calibracion',
    'firmado', 'novedad_id', 'tenant_id',
])]
class Calibracion extends Model
{
    use SoftDeletes;

    /**
     * Get the orden de trabajo this calibracion belongs to.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * Get the laboratorio where this calibracion was performed.
     *
     * @return BelongsTo<Laboratorio, $this>
     */
    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(Laboratorio::class);
    }

    /**
     * Get the area that solicited this calibracion.
     *
     * @return BelongsTo<Area, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'solicitante_id');
    }

    /**
     * Get the tecnico that performed this calibracion.
     *
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the procedimiento used for this calibracion.
     *
     * @return BelongsTo<ProcedimientoCalibracion, $this>
     */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoCalibracion::class);
    }

    /**
     * Get the novedad reported during this calibracion.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class);
    }

    /**
     * Get the tenant this calibracion belongs to.
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
            'temperatura' => 'decimal:2',
            'humedad' => 'decimal:2',
            'ajustes_requeridos' => 'boolean',
            'firmado' => 'boolean',
        ];
    }
}
