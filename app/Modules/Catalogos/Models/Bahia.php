<?php

namespace App\Modules\Catalogos\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Ingresos\Models\Ingreso;
use Database\Factories\BahiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $nombre
 * @property int $area_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Area $area
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'area_id', 'tenant_id'])]
#[UseFactory(BahiaFactory::class)]
class Bahia extends Model
{
    /** @use HasFactory<BahiaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Las bahías del tenant con su área, cantidad de equipos y usuarios y clientes asignados,
     * como las muestra el listado de bahías y las usan los modales de asignar.
     *
     * @return Collection<int, array{id: int, nombre: string, area_id: int, area_nombre: string, equipos_count: int, usuario_ids: Collection<int, int>, cliente_ids: Collection<int, int>}>
     */
    public static function listadoConUsuarios(int $tenantId): Collection
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->with(['area:id,nombre', 'usuarios:id', 'clientes:id'])
            ->withCount('equipo as equipos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'area_id'])
            ->map(fn (Bahia $bahia) => [
                'id' => $bahia->id,
                'nombre' => $bahia->nombre,
                'area_id' => $bahia->area_id,
                'area_nombre' => $bahia->area->nombre,
                'equipos_count' => (int) $bahia->getAttribute('equipos_count'),
                'usuario_ids' => $bahia->usuarios->pluck('id')->values(),
                'cliente_ids' => $bahia->clientes->pluck('id')->values(),
            ]);
    }

    /**
     * Get the area this bahia belongs to.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the tenant this bahia belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos located in this bahia.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the usuarios assigned to this bahia.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the clientes assigned to this bahia.
     *
     * @return BelongsToMany<Cliente, $this>
     */
    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class)->withTimestamps();
    }

    /**
     * Get the ingresos received in this bahia.
     *
     * @return HasMany<Ingreso, $this>
     */
    public function ingreso(): HasMany
    {
        return $this->hasMany(Ingreso::class);
    }
}
