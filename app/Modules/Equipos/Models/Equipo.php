<?php

namespace App\Modules\Equipos\Models;

use App\Models\Tenant;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Catalogos\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int $tipo_equipo_id
 * @property string $tipo_tecnologia
 * @property string $modelo
 * @property int $fabricante_id
 * @property string $numero_serie
 * @property array<string, mixed>|null $ficha_tecnica
 * @property int $area_id
 * @property int $bahia_id
 * @property string $condicion_actual
 * @property string|null $criticidad ALTA, MEDIA o BAJA; nula si aún no se asignó
 * @property string|null $notas
 * @property bool $activo
 * @property string|null $motivo_inactivo Por qué el sistema lo inactivó (ver inactivar()); nulo si está activo o se inactivó a mano
 * @property bool $patron_referencia
 * @property-read string $codigo_nombre Calculado: "Tipo de equipo - código" (requiere tipoEquipo)
 * @property bool $requiere_programacion
 * @property int $cliente_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TipoEquipo $tipoEquipo
 * @property-read Fabricante $fabricante
 * @property-read Area $area
 * @property-read Bahia $bahia
 * @property-read Cliente $cliente
 * @property-read Tenant $tenant
 * @property-read EquipoEspecificacionTecnica|null $equipoEspecificacionTecnica
 * @property-read Collection<int, EquipoProgramacion> $equipoProgramaciones
 * @property-read Collection<int, EquipoHistorico> $equipoHistoricos
 */
#[Fillable([
    'codigo', 'tipo_equipo_id', 'tipo_tecnologia', 'modelo', 'fabricante_id',
    'numero_serie', 'ficha_tecnica', 'area_id', 'bahia_id', 'condicion_actual', 'criticidad',
    'notas', 'activo', 'motivo_inactivo', 'patron_referencia', 'requiere_programacion', 'cliente_id', 'tenant_id',
])]
class Equipo extends Model
{
    use SoftDeletes;

    /**
     * Al volver a activar el equipo se olvida por qué estaba inactivo.
     */
    protected static function booted(): void
    {
        static::saving(function (Equipo $equipo): void {
            if ($equipo->activo) {
                $equipo->motivo_inactivo = null;
            }
        });
    }

    /**
     * Deja el equipo inactivo, guardando el motivo para mostrarlo en el listado de equipos
     * (ej. "Calibración: Fuera de servicio").
     */
    public function inactivar(string $motivo): void
    {
        $this->update(['activo' => false, 'motivo_inactivo' => $motivo]);
    }

    /**
     * Inactiva el equipo si la novedad reportada en el módulo está marcada para hacerlo
     * (ver Novedad::$inactivar_equipo).
     */
    public function inactivarSiLaNovedadLoPide(?int $novedadId, string $modulo): void
    {
        $novedad = $novedadId === null ? null : Novedad::query()->find($novedadId);

        if ($novedad?->inactivar_equipo) {
            $this->inactivar("Novedad en {$modulo}: {$novedad->nombre}");
        }
    }

    /**
     * Código y nombre del equipo concatenados, ej. "Manómetro - EQ-0001". Se calcula en vez
     * de guardarse para que, si cambia la regla, aplique a todos los equipos.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function codigoNombre(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->tipoEquipo->nombre} - {$this->codigo}");
    }

    /**
     * Get the tipo de equipo this equipo belongs to.
     *
     * @return BelongsTo<TipoEquipo, $this>
     */
    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * Get the fabricante of this equipo.
     *
     * @return BelongsTo<Fabricante, $this>
     */
    public function fabricante(): BelongsTo
    {
        return $this->belongsTo(Fabricante::class);
    }

    /**
     * Get the area this equipo is located in.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the bahia this equipo is located in.
     *
     * @return BelongsTo<Bahia, $this>
     */
    public function bahia(): BelongsTo
    {
        return $this->belongsTo(Bahia::class);
    }

    /**
     * Get the cliente that owns this equipo.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Get the tenant this equipo belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the especificacion tecnica of this equipo.
     *
     * @return HasOne<EquipoEspecificacionTecnica, $this>
     */
    public function equipoEspecificacionTecnica(): HasOne
    {
        return $this->hasOne(EquipoEspecificacionTecnica::class);
    }

    /**
     * Get the programaciones de servicio of this equipo.
     *
     * An equipo can have more than one (e.g. one for mantenimiento and another for calibración).
     *
     * @return HasMany<EquipoProgramacion, $this>
     */
    public function equipoProgramaciones(): HasMany
    {
        return $this->hasMany(EquipoProgramacion::class);
    }

    /**
     * Get the documentos of this equipo.
     *
     * @return HasMany<DocumentoEquipo, $this>
     */
    public function equipoDocumentos(): HasMany
    {
        return $this->hasMany(DocumentoEquipo::class);
    }

    /**
     * Get the servicios históricos of this equipo, imported from other systems.
     *
     * @return HasMany<EquipoHistorico, $this>
     */
    public function equipoHistoricos(): HasMany
    {
        return $this->hasMany(EquipoHistorico::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ficha_tecnica' => 'array',
            'activo' => 'boolean',
            'patron_referencia' => 'boolean',
            'requiere_programacion' => 'boolean',
        ];
    }
}
