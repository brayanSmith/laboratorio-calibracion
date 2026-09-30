<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $equipo_id
 * @property string $tipo_servicio
 * @property string|null $intervalo_servicio
 * @property string|null $intervalo_unidad
 * @property Carbon|null $fecha_apertura_historial_servicio
 * @property Carbon|null $fecha_ultimo_servicio
 * @property Carbon|null $fecha_proximo_servicio
 * @property string $dias_plazo_vencimiento
 * @property-read string $estado_vencimiento Calculado a partir de fecha_proximo_servicio y dias_plazo_vencimiento
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read Tenant $tenant
 */
#[Fillable([
    'equipo_id', 'tipo_servicio', 'intervalo_servicio', 'intervalo_unidad', 'fecha_apertura_historial_servicio',
    'fecha_ultimo_servicio', 'fecha_proximo_servicio', 'dias_plazo_vencimiento', 'tenant_id',
])]
class EquipoProgramacion extends Model
{
    use SoftDeletes;

    /**
     * The accessors to append to the model's array/JSON form.
     *
     * @var array<int, string>
     */
    protected $appends = ['estado_vencimiento'];

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
     * Calculate fecha_proximo_servicio as fecha_ultimo_servicio plus intervalo_servicio,
     * interpreted in intervalo_unidad (DIAS, SEMANAS or MESES). Returns null when any
     * of the three pieces is missing.
     */
    public static function calcularFechaProximoServicio(?string $fechaUltimoServicio, mixed $intervalo, ?string $unidad): ?string
    {
        if (! $fechaUltimoServicio || ! $intervalo || ! $unidad) {
            return null;
        }

        $fecha = match ($unidad) {
            'DIAS' => Carbon::parse($fechaUltimoServicio)->addDays((int) $intervalo),
            'SEMANAS' => Carbon::parse($fechaUltimoServicio)->addWeeks((int) $intervalo),
            'MESES' => Carbon::parse($fechaUltimoServicio)->addMonths((int) $intervalo),
            default => null,
        };

        return $fecha?->toDateString();
    }

    /**
     * El estado de vencimiento no se guarda: se calcula comparando fecha_proximo_servicio
     * con hoy, usando dias_plazo_vencimiento como margen para "próximo a vencer".
     *
     * @return Attribute<string, never>
     */
    protected function estadoVencimiento(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (! $this->fecha_proximo_servicio) {
                    return 'AL_DIA';
                }

                $diasRestantes = now()->startOfDay()->diffInDays($this->fecha_proximo_servicio, false);

                return match (true) {
                    $diasRestantes < 0 => 'VENCIDO',
                    $diasRestantes <= (float) $this->dias_plazo_vencimiento => 'PROXIMO_A_VENCER',
                    default => 'AL_DIA',
                };
            },
        );
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
