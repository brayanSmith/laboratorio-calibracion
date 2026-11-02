<?php

namespace App\Modules\OrdenesTrabajo\Models;

use App\Models\Tenant;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Http\Controllers\OrdenTrabajoController;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $codigo
 * @property int $equipo_programacion_id
 * @property Carbon $fecha_programada_orden_trabajo
 * @property string $estado
 * @property bool $listo_para_mantenimiento Marcado desde "Agendar Mantenimiento"
 * @property bool $mantenimiento_asignado_tercero
 * @property bool $mantenimiento_finalizado Se marca al finalizar el mantenimiento
 * @property bool $devolucion Se marca al crearse por una calibración devuelta a mantenimiento
 * @property bool $listo_para_calibracion
 * @property bool $calibracion_asignado_tercero
 * @property bool $calibracion_finalizado Se marca al finalizar la calibración
 * @property bool $orden_trabajo_programada
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Despacho|null $despacho
 * @property-read EquipoProgramacion $equipoProgramacion
 * @property-read Tenant $tenant
 * @property-read string $estadoVencimiento Proviene de equipoProgramacion: no se duplica aquí
 * @property-read bool $requiereCalibracion Se deriva del tipo_servicio de la programación
 */
#[Fillable([
    'codigo', 'equipo_programacion_id',
    'fecha_programada_orden_trabajo', 'estado', 'listo_para_mantenimiento',
    'mantenimiento_asignado_tercero', 'mantenimiento_finalizado', 'devolucion', 'listo_para_calibracion',
    'calibracion_asignado_tercero', 'calibracion_finalizado',
    'orden_trabajo_programada', 'tenant_id',
])]
class OrdenTrabajo extends Model
{
    use SoftDeletes;

    /**
     * Get the despacho that closed this orden de trabajo.
     *
     * @return HasOne<Despacho, $this>
     */
    public function despacho(): HasOne
    {
        return $this->hasOne(Despacho::class);
    }

    /**
     * Get the mantenimientos (propios) of this orden de trabajo.
     *
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    /**
     * Get the calibraciones (propias) of this orden de trabajo.
     *
     * @return HasMany<Calibracion, $this>
     */
    public function calibraciones(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }

    /**
     * Get the servicios (mantenimiento o calibración) that empresas terceras did on this orden.
     *
     * @return HasMany<ServicioTercero, $this>
     */
    public function serviciosTerceros(): HasMany
    {
        return $this->hasMany(ServicioTercero::class);
    }

    /**
     * Get the programacion de servicio that originated this orden de trabajo. El equipo,
     * el ingreso y si el equipo llegó o no se consultan a través de ella (ej.
     * $ordenTrabajo->equipoProgramacion->equipo, ->ingreso, o estado_programacion).
     *
     * @return BelongsTo<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): BelongsTo
    {
        return $this->belongsTo(EquipoProgramacion::class);
    }

    /**
     * Get the tenant this orden de trabajo belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to the ordenes listas para "Agendar Calibraciones" (ver
     * OrdenTrabajoController::equiposListosCalibracion()): su mantenimiento ya terminó,
     * su calibración todavía no, y no está ya agendada. Si el mantenimiento dejó el equipo
     * fuera de servicio, no sigue a calibración.
     *
     * @param  Builder<OrdenTrabajo>  $query
     */
    public function scopeListosParaCalibracion(Builder $query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId)
            ->where('mantenimiento_finalizado', true)
            ->where('calibracion_finalizado', false)
            ->where('listo_para_calibracion', false)
            ->whereNotExists(self::mantenimientoFueraDeServicio(...));
    }

    /**
     * Subconsulta: el mantenimiento de la orden terminó con el equipo fuera de servicio.
     */
    public static function mantenimientoFueraDeServicio(QueryBuilder $query): void
    {
        $query->select(DB::raw(1))
            ->from('mantenimientos')
            ->whereColumn('mantenimientos.orden_trabajo_id', 'orden_trabajos.id')
            ->where('mantenimientos.estado_final_equipo', 'FUERA_DE_SERVICIO')
            ->whereNull('mantenimientos.deleted_at');
    }

    /**
     * El vencimiento de esta orden es el mismo de la programación que la originó: no se
     * guarda por separado, se lee de ahí para que no se puedan desincronizar.
     *
     * @return Attribute<string, never>
     */
    protected function estadoVencimiento(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->equipoProgramacion->estado_vencimiento,
        );
    }

    /**
     * Si la programación que originó esta orden incluye CALIBRACION en su tipo_servicio
     * (CSV): no se guarda por separado, se lee de ahí para que no se puedan desincronizar.
     *
     * @return Attribute<bool, never>
     */
    protected function requiereCalibracion(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => in_array('CALIBRACION', explode(',', $this->equipoProgramacion->tipo_servicio), true),
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
            'fecha_programada_orden_trabajo' => 'date',
            'listo_para_mantenimiento' => 'boolean',
            'mantenimiento_asignado_tercero' => 'boolean',
            'mantenimiento_finalizado' => 'boolean',
            'devolucion' => 'boolean',
            'listo_para_calibracion' => 'boolean',
            'calibracion_asignado_tercero' => 'boolean',
            'calibracion_finalizado' => 'boolean',
            'orden_trabajo_programada' => 'boolean',
        ];
    }
}
