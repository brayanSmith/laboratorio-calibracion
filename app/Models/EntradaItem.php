<?php

namespace App\Models;

use Database\Factories\EntradaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $precio_total
 * @property string|null $codigo_compra
 * @property bool $recibido
 * @property Carbon|null $fecha_recibido
 * @property string|null $observaciones
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Item $item
 * @property-read Tenant $tenant
 */
#[Fillable([
    'item_id',
    'cantidad',
    'precio_unitario',
    'precio_total',
    'codigo_compra',
    'recibido',
    'fecha_recibido',
    'observaciones',
    'tenant_id',
])]
class EntradaItem extends Model
{
    /** @use HasFactory<EntradaItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the item this entrada belongs to.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the tenant this record belongs to.
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
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'precio_total' => 'decimal:2',
            'recibido' => 'boolean',
            'fecha_recibido' => 'date',
        ];
    }
}
