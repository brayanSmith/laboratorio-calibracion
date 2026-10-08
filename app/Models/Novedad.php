<?php

namespace App\Models;

use Database\Factories\NovedadFactory;
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
 * @property string $categoria
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'categoria', 'tenant_id'])]
class Novedad extends Model
{
    /** @use HasFactory<NovedadFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The categorias a novedad can belong to, matching the enum of the novedads table.
     *
     * @var array<int, string>
     */
    public const CATEGORIAS = ['INGRESO', 'MANTENIMIENTO', 'CALIBRACION', 'SALIDA'];

    /**
     * Get the tenant this novedad belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the calibraciones that reported this novedad.
     *
     * @return HasMany<Calibracion, $this>
     */
    public function calibracion(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }

    /**
     * Get the mantenimientos that reported this novedad.
     *
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimiento(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    /**
     * Get the despachos that reported this novedad.
     *
     * @return HasMany<Despacho, $this>
     */
    public function despacho(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    /**
     * Get the programaciones de equipo cancelled because of this novedad.
     *
     * @return HasMany<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): HasMany
    {
        return $this->hasMany(EquipoProgramacion::class, 'novedad_ingreso_id');
    }
}
