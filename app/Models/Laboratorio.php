<?php

namespace App\Models;

use Database\Factories\LaboratorioFactory;
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
 * @property string|null $descripcion
 * @property string|null $direccion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'descripcion', 'direccion', 'tenant_id'])]
class Laboratorio extends Model
{
    /** @use HasFactory<LaboratorioFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this laboratorio belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the calibraciones done in this laboratorio.
     *
     * @return HasMany<Calibracion, $this>
     */
    public function calibracion(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }
}
