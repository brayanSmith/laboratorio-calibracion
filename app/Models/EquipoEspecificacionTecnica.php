<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $equipo_id
 * @property int $tipo_magnitud_id
 * @property int $unidad_medida_id
 * @property string $alcance_indicacion
 * @property string $precision
 * @property string $resolucion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read TipoMagnitud $tipoMagnitud
 * @property-read UnidadMedida $unidadMedida
 * @property-read Tenant $tenant
 */
#[Fillable(['equipo_id', 'tipo_magnitud_id', 'unidad_medida_id', 'alcance_indicacion', 'precision', 'resolucion', 'tenant_id'])]
class EquipoEspecificacionTecnica extends Model
{
    use SoftDeletes;

    /**
     * Get the equipo this especificacion tecnica belongs to.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the tipo de magnitud of this especificacion.
     *
     * @return BelongsTo<TipoMagnitud, $this>
     */
    public function tipoMagnitud(): BelongsTo
    {
        return $this->belongsTo(TipoMagnitud::class);
    }

    /**
     * Get the unidad de medida of this especificacion.
     *
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    /**
     * Get the tenant this especificacion tecnica belongs to.
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
            'alcance_indicacion' => 'decimal:2',
            'precision' => 'decimal:2',
            'resolucion' => 'decimal:2',
        ];
    }
}
