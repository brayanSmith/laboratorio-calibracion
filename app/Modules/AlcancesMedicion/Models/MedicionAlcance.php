<?php

namespace App\Modules\AlcancesMedicion\Models;

use App\Models\Tenant;
use App\Modules\Catalogos\Models\TipoEquipo;
use Database\Factories\MedicionAlcanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tipo_equipo_id
 * @property string $modelo
 * @property string $alcance_indicacion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TipoEquipo $tipoEquipo
 * @property-read Tenant $tenant
 */
#[Fillable(['tipo_equipo_id', 'modelo', 'alcance_indicacion', 'tenant_id'])]
#[UseFactory(MedicionAlcanceFactory::class)]
class MedicionAlcance extends Model
{
    /** @use HasFactory<MedicionAlcanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Símbolo de la precisión ("%" o "±") de los equipos que cubre un alcance. Determina si el EMP
     * de sus detalles es un porcentaje o un valor absoluto; sin equipos se asume "±".
     */
    public static function simboloPrecision(mixed $tenantId, mixed $tipoEquipoId, mixed $modelo, mixed $alcanceIndicacion): string
    {
        $precision = DB::table('equipo_especificacion_tecnicas')
            ->join('equipos', 'equipos.id', '=', 'equipo_especificacion_tecnicas.equipo_id')
            ->where('equipo_especificacion_tecnicas.tenant_id', $tenantId)
            ->where('equipos.tipo_equipo_id', $tipoEquipoId)
            ->where('equipos.modelo', $modelo)
            ->where('equipo_especificacion_tecnicas.alcance_indicacion', $alcanceIndicacion)
            ->whereNull('equipo_especificacion_tecnicas.deleted_at')
            ->whereNull('equipos.deleted_at')
            ->orderBy('equipo_especificacion_tecnicas.id')
            ->value('equipo_especificacion_tecnicas.precision');

        return is_string($precision) && str_ends_with($precision, '%') ? '%' : '±';
    }

    /**
     * Get the tipo de equipo this medicion de alcance belongs to.
     *
     * @return BelongsTo<TipoEquipo, $this>
     */
    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * Get the detalles of this medicion de alcance.
     *
     * @return HasMany<DetalleMedicionAlcance, $this>
     */
    public function detalleMedicionAlcance(): HasMany
    {
        return $this->hasMany(DetalleMedicionAlcance::class);
    }

    /**
     * Get the tenant this medicion de alcance belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
