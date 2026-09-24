<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $medicion_alcance_id
 * @property int $unidad_medida_id
 * @property string $valor_instrumento
 * @property string $emp
 * @property string $incertidumbre
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read MedicionAlcance $medicionAlcance
 * @property-read UnidadMedida $unidadMedida
 * @property-read Tenant $tenant
 */
#[Fillable(['medicion_alcance_id', 'unidad_medida_id', 'valor_instrumento', 'emp', 'incertidumbre', 'tenant_id'])]
class DetalleMedicionAlcance extends Model
{
    use SoftDeletes;

    /**
     * Get the medicion de alcance this detalle belongs to.
     *
     * @return BelongsTo<MedicionAlcance, $this>
     */
    public function medicionAlcance(): BelongsTo
    {
        return $this->belongsTo(MedicionAlcance::class);
    }

    /**
     * Get the unidad de medida of this detalle.
     *
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    /**
     * Get the tenant this detalle belongs to.
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
            'valor_instrumento' => 'decimal:2',
            'emp' => 'decimal:2',
            'incertidumbre' => 'decimal:2',
        ];
    }
}
