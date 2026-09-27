<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
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
 * @property string $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'email', 'telefono', 'direccion', 'tenant_id'])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this cliente belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos owned by this cliente.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the despachos where this cliente received the equipos.
     *
     * @return HasMany<Despacho, $this>
     */
    public function despachoRecibido(): HasMany
    {
        return $this->hasMany(Despacho::class, 'cliente_recibe_id');
    }

    /**
     * Get the ingresos where this cliente delivered the equipos.
     *
     * @return HasMany<Ingreso, $this>
     */
    public function ingresoEntregado(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'cliente_entrega_id');
    }
}
