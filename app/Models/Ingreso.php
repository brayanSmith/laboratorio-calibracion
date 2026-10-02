<?php

namespace App\Models;

use Database\Factories\IngresoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $bahia_id
 * @property Carbon $desde
 * @property Carbon $hasta
 * @property int|null $tecnico_recibe_id Nulo hasta que se edita el ingreso
 * @property int|null $cliente_entrega_id Nulo hasta que se edita el ingreso
 * @property string|null $firma_cliente_entrega
 * @property string $estado_ingreso PENDIENTE, INGRESADO o CANCELADO
 * @property string|null $novedad Notas generales cuando el ingreso queda aprobado
 * @property string|null $motivo_cancelacion Solo cuando estado_ingreso es CANCELADO
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Bahia $bahia
 * @property-read User|null $tecnicoRecibe
 * @property-read Cliente|null $clienteEntrega
 * @property-read Tenant $tenant
 */
#[Fillable([
    'bahia_id', 'desde', 'hasta', 'tecnico_recibe_id', 'cliente_entrega_id',
    'firma_cliente_entrega', 'estado_ingreso', 'novedad', 'motivo_cancelacion', 'tenant_id',
])]
class Ingreso extends Model
{
    /** @use HasFactory<IngresoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the public URL path of the cliente signature, if it has one.
     */
    public function firmaUrl(): ?string
    {
        if (! $this->firma_cliente_entrega) {
            return null;
        }

        $path = parse_url(Storage::disk('public')->url($this->firma_cliente_entrega), PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    /**
     * Get the ordenes de trabajo originated by this ingreso.
     *
     * @return HasMany<OrdenTrabajo, $this>
     */
    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

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
     * Get the programaciones de servicio brought in by this ingreso.
     *
     * @return HasMany<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): HasMany
    {
        return $this->hasMany(EquipoProgramacion::class);
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
        ];
    }
}
