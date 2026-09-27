<?php

namespace App\Models;

use Database\Factories\UnidadMedidaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $simbolo
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'simbolo', 'tenant_id'])]
class UnidadMedida extends Model
{
    /** @use HasFactory<UnidadMedidaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this unidad de medida belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the especificaciones técnicas that use this unidad de medida.
     *
     * @return HasMany<EquipoEspecificacionTecnica, $this>
     */
    public function equipoEspecificacionTecnica(): HasMany
    {
        return $this->hasMany(EquipoEspecificacionTecnica::class);
    }

    /**
     * Get the detalles de medición de alcance that use this unidad de medida.
     *
     * @return HasMany<DetalleMedicionAlcance, $this>
     */
    public function detalleMedicionAlcance(): HasMany
    {
        return $this->hasMany(DetalleMedicionAlcance::class);
    }

    /**
     * Get the detalles de medición de calibración that use this unidad de medida.
     *
     * @return HasMany<DetalleMedicionCalibracion, $this>
     */
    public function detalleMedicionCalibracion(): HasMany
    {
        return $this->hasMany(DetalleMedicionCalibracion::class);
    }
}
