<?php

namespace App\Models;

use Database\Factories\BahiaFactory;
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
 * @property int $area_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Area $area
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'area_id', 'tenant_id'])]
class Bahia extends Model
{
    /** @use HasFactory<BahiaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the area this bahia belongs to.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the tenant this bahia belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos located in this bahia.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the ingresos received in this bahia.
     *
     * @return HasMany<Ingreso, $this>
     */
    public function ingreso(): HasMany
    {
        return $this->hasMany(Ingreso::class);
    }
}
