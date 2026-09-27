<?php

namespace App\Models;

use Database\Factories\TipoMagnitudFactory;
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
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'tenant_id'])]
class TipoMagnitud extends Model
{
    /** @use HasFactory<TipoMagnitudFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this tipo de magnitud belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the especificaciones técnicas that use this tipo de magnitud.
     *
     * @return HasMany<EquipoEspecificacionTecnica, $this>
     */
    public function equipoEspecificacionTecnica(): HasMany
    {
        return $this->hasMany(EquipoEspecificacionTecnica::class);
    }
}
