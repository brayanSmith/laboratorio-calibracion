<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tecnico_entrega_id
 * @property int $cliente_recibe_id
 * @property string|null $firma_cliente_recibe
 * @property int $novedad_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read User $tecnicoEntrega
 * @property-read Cliente $clienteRecibe
 * @property-read Novedad $novedad
 */
#[Fillable(['tenant_id', 'tecnico_entrega_id', 'cliente_recibe_id', 'firma_cliente_recibe', 'novedad_id'])]
class Despacho extends Model
{
    use SoftDeletes;

    /**
     * Get the tenant this despacho belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the tecnico that delivered the equipo.
     *
     * @return BelongsTo<User, $this>
     */
    public function tecnicoEntrega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_entrega_id');
    }

    /**
     * Get the cliente that received the equipo.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function clienteRecibe(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_recibe_id');
    }

    /**
     * Get the novedad related to this despacho.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class);
    }
}
