<?php

namespace App\Modules\Catalogos\Models;

use App\Models\Tenant;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Database\Factories\NovedadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $categoria
 * @property bool $inactivar_equipo Si al reportarla el equipo queda inactivo (ver Equipo::inactivarSiLaNovedadLoPide())
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'categoria', 'inactivar_equipo', 'tenant_id'])]
#[UseFactory(NovedadFactory::class)]
class Novedad extends Model
{
    /** @use HasFactory<NovedadFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The categorias a novedad can belong to, matching the enum of the novedads table.
     *
     * @var array<int, string>
     */
    public const CATEGORIAS = ['INGRESO', 'MANTENIMIENTO', 'CALIBRACION', 'SALIDA'];

    /**
     * Get the tenant this novedad belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the calibraciones that reported this novedad.
     *
     * @return HasMany<Calibracion, $this>
     */
    public function calibracion(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }

    /**
     * Get the mantenimientos that reported this novedad.
     *
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimiento(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    /**
     * Get the despachos that reported this novedad.
     *
     * @return HasMany<Despacho, $this>
     */
    public function despacho(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    /**
     * Get the programaciones de equipo cancelled because of this novedad.
     *
     * @return HasMany<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): HasMany
    {
        return $this->hasMany(EquipoProgramacion::class, 'novedad_ingreso_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inactivar_equipo' => 'boolean',
        ];
    }
}
