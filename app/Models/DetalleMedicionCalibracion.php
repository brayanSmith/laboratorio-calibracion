<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $calibracion_id
 * @property int $detalle_medicion_alcance_id
 * @property string $valor_referencia
 * @property int $unidad_medida_id
 * @property string $valor_instrumento
 * @property string $error_encontrado
 * @property string $emp
 * @property string $incertidumbre
 * @property string $error_porcentaje
 * @property string $emp_porcentaje_positivo
 * @property string $emp_porcentaje_negativo
 * @property string $resultado_calibracion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Calibracion $calibracion
 * @property-read DetalleMedicionAlcance $detalleMedicionAlcance
 * @property-read UnidadMedida $unidadMedida
 * @property-read Tenant $tenant
 */
#[Fillable([
    'calibracion_id', 'detalle_medicion_alcance_id', 'valor_referencia', 'unidad_medida_id',
    'valor_instrumento', 'error_encontrado', 'emp', 'incertidumbre', 'error_porcentaje',
    'emp_porcentaje_positivo', 'emp_porcentaje_negativo', 'resultado_calibracion', 'tenant_id',
])]
class DetalleMedicionCalibracion extends Model
{
    use SoftDeletes;

    /**
     * Get the calibracion this detalle belongs to.
     *
     * @return BelongsTo<Calibracion, $this>
     */
    public function calibracion(): BelongsTo
    {
        return $this->belongsTo(Calibracion::class);
    }

    /**
     * Get the detalle de medicion de alcance this measurement is based on.
     *
     * @return BelongsTo<DetalleMedicionAlcance, $this>
     */
    public function detalleMedicionAlcance(): BelongsTo
    {
        return $this->belongsTo(DetalleMedicionAlcance::class);
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
            'valor_referencia' => 'decimal:2',
            'valor_instrumento' => 'decimal:2',
            'error_encontrado' => 'decimal:2',
            'emp' => 'decimal:2',
            'incertidumbre' => 'decimal:2',
            'error_porcentaje' => 'decimal:2',
            'emp_porcentaje_positivo' => 'decimal:2',
            'emp_porcentaje_negativo' => 'decimal:2',
            'resultado_calibracion' => 'decimal:2',
        ];
    }
}
