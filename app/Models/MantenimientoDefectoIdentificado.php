<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mantenimiento_id
 * @property string $defecto_identificado
 * @property string $nivel_riesgo
 * @property string|null $accion_correctiva
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Mantenimiento $mantenimiento
 * @property-read Tenant $tenant
 */
#[Fillable(['mantenimiento_id', 'defecto_identificado', 'nivel_riesgo', 'accion_correctiva', 'tenant_id'])]
class MantenimientoDefectoIdentificado extends Model
{
    use SoftDeletes;

    /**
     * Get the mantenimiento this defecto belongs to.
     *
     * @return BelongsTo<Mantenimiento, $this>
     */
    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /**
     * Get the tenant this defecto belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
