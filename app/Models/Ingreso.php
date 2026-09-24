<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bahia_id
 * @property Carbon $desde
 * @property Carbon $hasta
 * @property int $tecnico_recibe_id
 * @property int $cliente_entrega_id
 * @property string|null $firma_cliente_entrega
 * @property bool $ingreso_exitoso
 * @property string|null $novedad
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Bahia $bahia
 * @property-read User $tecnicoRecibe
 * @property-read Cliente $clienteEntrega
 * @property-read Tenant $tenant
 */
#[Fillable([
    'bahia_id', 'desde', 'hasta', 'tecnico_recibe_id', 'cliente_entrega_id',
    'firma_cliente_entrega', 'ingreso_exitoso', 'novedad', 'tenant_id',
])]
class Ingreso extends Model
{
    use SoftDeletes;

    /**
     * Get the bahia this ingreso happened in.
     *
     * @return BelongsTo<Bahia, $this>
     */
    public function bahia(): BelongsTo
    {
        return $this->belongsTo(Bahia::class);
    }

    /**
     * Get the tecnico that received the equipo.
     *
     * @return BelongsTo<User, $this>
     */
    public function tecnicoRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_recibe_id');
    }

    /**
     * Get the cliente that delivered the equipo.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function clienteEntrega(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_entrega_id');
    }

    /**
     * Get the tenant this ingreso belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'ingreso_exitoso' => 'boolean',
        ];
    }
}
