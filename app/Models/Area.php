<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $direccion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'descripcion', 'direccion', 'tenant_id'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Texto para los selectores de área: "CRC - Centro de Reparación de Componentes".
     *
     * @return Attribute<string, never>
     */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->descripcion)
            ? "{$this->nombre} - {$this->descripcion}"
            : $this->nombre);
    }

    /**
     * Get the tenant this area belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos located in this area.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the bahias of this area.
     *
     * @return HasMany<Bahia, $this>
     */
    public function bahia(): HasMany
    {
        return $this->hasMany(Bahia::class);
    }
}
