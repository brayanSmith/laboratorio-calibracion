<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $orden_trabajo_id
 * @property int|null $tecnico_entrega_id Se completa al agendar el despacho
 * @property int|null $cliente_recibe_id Se completa cuando se realiza el despacho
 * @property bool $entrega_autorizada Se marca al finalizar la calibración (o el mantenimiento, si no requiere calibración)
 * @property string|null $firma_cliente_recibe
 * @property bool $entrega_recibida
 * @property int|null $novedad_id Se completa si surge una novedad durante el despacho
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read User|null $tecnicoEntrega
 * @property-read Cliente|null $clienteRecibe
 * @property-read Novedad|null $novedad
 */
#[Fillable(['tenant_id', 'orden_trabajo_id', 'tecnico_entrega_id', 'cliente_recibe_id', 'entrega_autorizada', 'firma_cliente_recibe', 'entrega_recibida', 'novedad_id'])]
class Despacho extends Model
{
    use SoftDeletes;

    /**
     * Get the public URL path of the cliente signature, if it has one.
     */
    public function firmaUrl(): ?string
    {
        if (! $this->firma_cliente_recibe) {
            return null;
        }

        $url = Storage::disk('public')->url($this->firma_cliente_recibe);

        if (config('filesystems.disks.public.driver') !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    /**
     * Scope a query to the despachos listos para "Agendar Despachos" (ver
     * OrdenTrabajoController::despachosListos()): todavía sin técnico de entrega.
     *
     * @param  Builder<Despacho>  $query
     */
    public function scopeListosParaAgendar(Builder $query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId)->whereNull('tecnico_entrega_id');
    }

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
     * Get the orden de trabajo this despacho closes.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entrega_autorizada' => 'boolean',
            'entrega_recibida' => 'boolean',
        ];
    }
}
