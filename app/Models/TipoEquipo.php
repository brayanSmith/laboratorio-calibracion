<?php

namespace App\Models;

use Database\Factories\TipoEquipoFactory;
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
 * @property string $tipo_mantenimiento
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'tipo_mantenimiento', 'tenant_id'])]
class TipoEquipo extends Model
{
    /** @use HasFactory<TipoEquipoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this tipo de equipo belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos of this tipo de equipo.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the mediciones de alcance of this tipo de equipo.
     *
     * @return HasMany<MedicionAlcance, $this>
     */
    public function medicionAlcance(): HasMany
    {
        return $this->hasMany(MedicionAlcance::class);
    }

    /**
     * Get the check list items of this tipo de equipo.
     *
     * @return HasMany<TipoEquipoCheckList, $this>
     */
    public function tipoEquipoCheckList(): HasMany
    {
        return $this->hasMany(TipoEquipoCheckList::class);
    }
}
