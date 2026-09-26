<?php

namespace App\Models;

use Database\Factories\EmpresaTerceroFactory;
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
 * @property string $nit
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'nit', 'direccion', 'telefono', 'email', 'tenant_id'])]
class EmpresaTercero extends Model
{
    /** @use HasFactory<EmpresaTerceroFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this empresa tercero belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the servicios prestados por esta empresa tercero.
     *
     * @return HasMany<ServicioTercero, $this>
     */
    public function servicioTercero(): HasMany
    {
        return $this->hasMany(ServicioTercero::class);
    }
}
