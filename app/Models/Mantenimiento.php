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
 * @property string $tipo_mantenimiento
 * @property Carbon $fecha_mantenimiento
 * @property string $descripcion
 * @property string $estado_inicial_equipo
 * @property string $estado_final_equipo
 * @property string $estado_mantenimiento
 * @property int $tecnico_id
 * @property bool $firmado
 * @property int $novedad_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read User $tecnico
 * @property-read Novedad $novedad
 * @property-read Tenant $tenant
 */
#[Fillable([
    'orden_trabajo_id', 'tipo_mantenimiento', 'fecha_mantenimiento', 'descripcion',
    'estado_inicial_equipo', 'estado_final_equipo', 'estado_mantenimiento',
    'tecnico_id', 'firmado', 'novedad_id', 'tenant_id',
])]
class Mantenimiento extends Model
{
    use SoftDeletes;

    /**
     * Get the orden de trabajo this mantenimiento belongs to.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * Get the tecnico that performed this mantenimiento.
     *
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the novedad reported during this mantenimiento.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class);
    }

    /**
     * Get the tenant this mantenimiento belongs to.
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
            'fecha_mantenimiento' => 'date',
            'firmado' => 'boolean',
        ];
    }
}
