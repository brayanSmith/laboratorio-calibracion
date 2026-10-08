<?php

namespace App\Models;

use Database\Factories\MedicionAlcanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tipo_equipo_id
 * @property string $alcance_indicacion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TipoEquipo $tipoEquipo
 * @property-read Tenant $tenant
 */
#[Fillable(['tipo_equipo_id', 'alcance_indicacion', 'tenant_id'])]
class MedicionAlcance extends Model
{
    /** @use HasFactory<MedicionAlcanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tipo de equipo this medicion de alcance belongs to.
     *
     * @return BelongsTo<TipoEquipo, $this>
     */
    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * Get the detalles of this medicion de alcance.
     *
     * @return HasMany<DetalleMedicionAlcance, $this>
     */
    public function detalleMedicionAlcance(): HasMany
    {
        return $this->hasMany(DetalleMedicionAlcance::class);
    }

    /**
     * Get the tenant this medicion de alcance belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
