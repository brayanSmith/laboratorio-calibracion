<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $equipo_id
 * @property string $tipo_servicio
 * @property string $tipo_mantenimiento PREVENTIVO o CORRECTIVO
 * @property string|null $falla_detectada Solo cuando tipo_mantenimiento es CORRECTIVO
 * @property string|null $intervalo_servicio
 * @property string|null $intervalo_unidad
 * @property Carbon|null $fecha_apertura_historial_servicio
 * @property Carbon|null $fecha_ultimo_servicio
 * @property Carbon|null $fecha_proximo_servicio
 * @property string $dias_plazo_vencimiento
 * @property-read string $estado_vencimiento Calculado a partir de fecha_proximo_servicio y dias_plazo_vencimiento
 * @property int|null $ingreso_id
 * @property bool $agendar Si la persona decide agendar este equipo al revisarlo en el ingreso
 * @property bool $ingresado Si el equipo efectivamente llegó, al recibir el ingreso
 * @property string $estado_programacion PENDIENTE, AGENDADO o CANCELADO
 * @property int|null $novedad_ingreso_id Novedad al recibir el ingreso, con o sin estado_programacion CANCELADO (ej. llegó pero incompleto)
 * @property string|null $observacion_no_ingreso Detalle adicional opcional
 * @property bool $re_agendar Solo tiene sentido junto con novedad_ingreso_id
 * @property array<string, mixed>|null $datos_re_agendamiento Fecha del próximo agendamiento, si re_agendar es true
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read Ingreso|null $ingreso
 * @property-read Novedad|null $novedadIngreso
 * @property-read Tenant $tenant
 * @property-read Collection<int, OrdenTrabajo> $ordenesTrabajo
 */
#[Fillable([
    'equipo_id', 'tipo_servicio', 'tipo_mantenimiento', 'falla_detectada',
    'intervalo_servicio', 'intervalo_unidad', 'fecha_apertura_historial_servicio',
    'fecha_ultimo_servicio', 'fecha_proximo_servicio', 'dias_plazo_vencimiento',
    'ingreso_id', 'agendar', 'ingresado', 'estado_programacion', 'novedad_ingreso_id', 'observacion_no_ingreso',
    're_agendar', 'datos_re_agendamiento', 'tenant_id',
])]
class EquipoProgramacion extends Model
{
    use SoftDeletes;

    /**
     * The accessors to append to the model's array/JSON form.
     *
     * @var list<string>
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
     * Get the ingreso that brought this programacion in for service, if any.
     *
     * @return BelongsTo<Ingreso, $this>
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    /**
     * Get the novedad explaining why the equipo did not come in, if cancelled.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedadIngreso(): BelongsTo
    {
        return $this->belongsTo(Novedad::class, 'novedad_ingreso_id');
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
     * Get the ordenes de trabajo originated by this programacion, if any.
     *
     * @return HasMany<OrdenTrabajo, $this>
     */
    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    /**
     * Scope a query to the programaciones listas para "Agendar Mantenimiento" (ver
     * OrdenTrabajoController::equiposListos()): ya ingresadas y sin una orden de
     * trabajo agendada todavía (sea porque no tienen ninguna, o porque la que tienen
     * es un placeholder de devolución pendiente de agendar).
     *
     * @param  Builder<EquipoProgramacion>  $query
     */
    public function scopeListosParaMantenimiento(Builder $query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId)
            ->where('ingresado', true)
            ->where(fn ($q) => $q
                ->whereDoesntHave('ordenesTrabajo')
                ->orWhereHas('ordenesTrabajo', fn ($q2) => $q2->where('listo_para_mantenimiento', false)))
            ->whereHas('ingreso', fn ($q) => $q->where('estado_ingreso', 'RECIBIDO'));
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
            'agendar' => 'boolean',
            'ingresado' => 'boolean',
            're_agendar' => 'boolean',
            'datos_re_agendamiento' => 'array',
        ];
    }
}
