<?php

namespace App\Modules\Despachos\Models;

use App\Concerns\RestringidoAlTecnico;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
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
 * @property Carbon|null $fecha_programada Día programado de la entrega; se completa al agendar el despacho
 * @property Carbon|null $fecha_despacho Día en que se entregó el equipo; se fija sola cuando la entrega queda recibida y no cambia
 * @property int|null $cliente_recibe_id Se completa cuando se realiza el despacho
 * @property bool $entrega_autorizada Se marca al finalizar la calibración (o el mantenimiento, si no requiere calibración)
 * @property string|null $firma_cliente_recibe
 * @property bool $entrega_recibida
 * @property string $estado_despacho PENDIENTE, AUTORIZADO o ENTREGADO; se calcula al guardar
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
#[Fillable(['tenant_id', 'orden_trabajo_id', 'tecnico_entrega_id', 'fecha_programada', 'cliente_recibe_id', 'entrega_autorizada', 'firma_cliente_recibe', 'entrega_recibida', 'novedad_id'])]
class Despacho extends Model
{
    use RestringidoAlTecnico;

    public static function permisoVerTodos(): TenantPermission
    {
        return TenantPermission::DespachosVerTodos;
    }

    public static function columnaTecnico(): string
    {
        return 'tecnico_entrega_id';
    }

    use SoftDeletes;

    /**
     * Keep estado_despacho derived from entrega_autorizada and entrega_recibida on
     * every save, so it can't drift from the two flags.
     */
    protected static function booted(): void
    {
        static::saving(function (Despacho $despacho): void {
            // Día real de la entrega: puede ser distinto del programado (fecha_programada). Se fija
            // la primera vez que queda recibida y no se vuelve a tocar.
            if ($despacho->entrega_recibida && $despacho->fecha_despacho === null) {
                $despacho->fecha_despacho = now()->toDateString();
            }

            $despacho->estado_despacho = match (true) {
                $despacho->entrega_recibida => 'ENTREGADO',
                $despacho->entrega_autorizada => 'AUTORIZADO',
                default => 'PENDIENTE',
            };
        });

        static::saved(function (Despacho $despacho): void {
            if ($despacho->novedad_id !== null && ($despacho->wasRecentlyCreated || $despacho->wasChanged('novedad_id'))) {
                $despacho->ordenTrabajo?->equipoProgramacion?->equipo?->inactivarSiLaNovedadLoPide($despacho->novedad_id, 'despacho');
            }

            if ($despacho->entrega_recibida && $despacho->wasChanged('entrega_recibida')) {
                $despacho->ordenTrabajo->equipoProgramacion->crearSiguienteProgramacionPreventiva(now());
            }

            if (! $despacho->entrega_autorizada) {
                return;
            }

            if ($despacho->wasRecentlyCreated || $despacho->wasChanged(['entrega_autorizada', 'estado_despacho'])) {
                $despacho->ordenTrabajo->equipoProgramacion->registrarSeguimiento('DESPACHO', $despacho->estado_despacho);
            }
        });
    }

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
            'fecha_programada' => 'date',
            'fecha_despacho' => 'date',
            'entrega_autorizada' => 'boolean',
            'entrega_recibida' => 'boolean',
        ];
    }
}
