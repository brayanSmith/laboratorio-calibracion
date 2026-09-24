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
 * @property string $tipo_servicio
 * @property string|null $intervalo_servicio
 * @property Carbon|null $fecha_apertura_historial_servicio
 * @property Carbon|null $fecha_ultimo_servicio
 * @property Carbon|null $fecha_proximo_servicio
 * @property string $dias_plazo_vencimiento
 * @property string $estado_vencimiento
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read Tenant $tenant
 */
#[Fillable([
    'equipo_id', 'tipo_servicio', 'intervalo_servicio', 'fecha_apertura_historial_servicio',
    'fecha_ultimo_servicio', 'fecha_proximo_servicio', 'dias_plazo_vencimiento',
    'estado_vencimiento', 'tenant_id',
])]
class EquipoProgramacion extends Model
{
    use SoftDeletes;

    /**
     * Get the equipo this programacion belongs to.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the tenant this programacion belongs to.
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
            'intervalo_servicio' => 'decimal:2',
            'fecha_apertura_historial_servicio' => 'date',
            'fecha_ultimo_servicio' => 'date',
            'fecha_proximo_servicio' => 'date',
            'dias_plazo_vencimiento' => 'decimal:2',
        ];
    }
}
