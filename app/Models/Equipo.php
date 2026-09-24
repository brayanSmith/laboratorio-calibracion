<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $notas
 * @property bool $activo
 * @property bool $patron_referencia
 * @property string|null $concatenar_codigo_nombre
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
 */
#[Fillable([
    'codigo', 'tipo_equipo_id', 'tipo_tecnologia', 'modelo', 'fabricante_id',
    'numero_serie', 'ficha_tecnica', 'area_id', 'bahia_id', 'condicion_actual',
    'notas', 'activo', 'patron_referencia', 'concatenar_codigo_nombre',
    'requiere_programacion', 'cliente_id', 'tenant_id',
])]
class Equipo extends Model
{
    use SoftDeletes;

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
