<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mantenimiento_id
 * @property int $item_id
 * @property string $cantidad
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Mantenimiento $mantenimiento
 * @property-read Item $item
 * @property-read Tenant $tenant
 */
#[Fillable(['mantenimiento_id', 'item_id', 'cantidad', 'tenant_id'])]
class ItemMantenimiento extends Model
{
    use SoftDeletes;

    /**
     * Get the mantenimiento this item belongs to.
     *
     * @return BelongsTo<Mantenimiento, $this>
     */
    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /**
     * Get the item used.
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
        ];
    }
}
