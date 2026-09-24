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
 * @property Carbon $fin
 * @property string $duracion
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
