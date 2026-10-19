<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $ubicacion
 * @property string|null $precio_unitario
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['codigo', 'nombre', 'descripcion', 'ubicacion', 'precio_unitario', 'tenant_id'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the tenant this item belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the mantenimiento usages of this item.
     *
     * @return HasMany<ItemMantenimiento, $this>
     */
    public function itemsMantenimiento(): HasMany
    {
        return $this->hasMany(ItemMantenimiento::class);
    }

    /**
     * Get the entradas of this item.
     *
     * @return HasMany<EntradaItem, $this>
     */
    public function entradas(): HasMany
    {
        return $this->hasMany(EntradaItem::class);
    }

    /**
     * Add the received entradas and the salidas needed to compute the stock.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function conMovimientos(Builder $query): Builder
    {
        return $query
            ->withSum(['entradas as entradas_recibidas' => fn ($entradas) => $entradas->where('recibido', true)], 'cantidad')
            ->withSum(['itemsMantenimiento as salidas_total' => fn ($usos) => $usos->where('con_stock', true)], 'cantidad');
    }

    /**
     * Stock: entradas recibidas menos salidas. Requires the conMovimientos scope.
     *
     * @return Attribute<float, never>
     */
    protected function stock(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->getAttribute('entradas_recibidas') - (float) $this->getAttribute('salidas_total'),
            2,
        ));
    }

    /**
     * Set the precio unitario from the most recently received entrada, if any.
     */
    public function actualizarPrecioDesdeUltimaEntrada(): void
    {
        $ultimaEntrada = $this->entradas()
            ->where('recibido', true)
            ->orderByDesc('fecha_recibido')
            ->orderByDesc('id')
            ->first();

        if ($ultimaEntrada !== null) {
            $this->update(['precio_unitario' => $ultimaEntrada->precio_unitario]);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
        ];
    }
}
