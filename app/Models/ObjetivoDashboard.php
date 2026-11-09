<?php

namespace App\Models;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Modules\Catalogos\Models\TipoEquipo;
use Database\Factories\ObjetivoDashboardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Objetivo de un indicador del dashboard: anual y general (ej. 5 días de tiempo promedio de
 * atención en 2026) o, en desempeño por técnico, por mes y tipo de equipo. El módulo siempre
 * sale del concepto: se completa solo al guardar.
 *
 * @property int $id
 * @property int $anio
 * @property int|null $mes Solo en los objetivos por mes (desempeño por técnico)
 * @property int|null $tipo_equipo_id Solo en los objetivos por tipo de equipo (desempeño por técnico)
 * @property ModuloObjetivo $modulo
 * @property ConceptoObjetivo $concepto
 * @property string $valor
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read TipoEquipo|null $tipoEquipo
 */
#[Fillable(['anio', 'mes', 'tipo_equipo_id', 'concepto', 'valor', 'tenant_id'])]
class ObjetivoDashboard extends Model
{
    /** @use HasFactory<ObjetivoDashboardFactory> */
    use HasFactory;

    protected $table = 'objetivos_dashboard';

    protected static function booted(): void
    {
        static::saving(function (self $objetivo): void {
            $objetivo->modulo = $objetivo->concepto->modulo();
        });
    }

    /**
     * Get the tenant this objetivo belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Objetivos de un módulo para los años dados, como [año => [concepto => valor]].
     *
     * @param  list<int>  $anios
     * @return array<int, array<string, float>>
     */
    public static function porAnio(int $tenantId, ModuloObjetivo $modulo, array $anios): array
    {
        return self::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->whereIn('anio', $anios)
            ->whereNull('mes')
            ->get()
            ->groupBy('anio')
            ->map(fn ($objetivos) => $objetivos
                ->mapWithKeys(fn (self $objetivo) => [$objetivo->concepto->value => (float) $objetivo->valor])
                ->all())
            ->all();
    }

    /**
     * Objetivos por mes y tipo de equipo de un módulo para los años dados (solo de los tipos
     * dados; sin tipos, de todos), uno por año, mes y tipo con sus valores por concepto.
     *
     * @param  list<int>  $anios
     * @param  list<int>  $tiposEquipo
     * @return list<array{anio: int, mes: int, tipo_equipo_id: int, valores: array<string, float>}>
     */
    public static function mensualesPorTipo(int $tenantId, ModuloObjetivo $modulo, array $anios, array $tiposEquipo = []): array
    {
        return self::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->whereIn('anio', $anios)
            ->whereNotNull('mes')
            ->when($tiposEquipo !== [], fn ($query) => $query->whereIn('tipo_equipo_id', $tiposEquipo))
            ->orderBy('anio')
            ->orderBy('mes')
            ->get()
            ->groupBy(fn (self $objetivo): string => "{$objetivo->anio}|{$objetivo->mes}|{$objetivo->tipo_equipo_id}")
            ->map(fn ($objetivos): array => [
                'anio' => $objetivos->first()->anio,
                'mes' => $objetivos->first()->mes,
                'tipo_equipo_id' => $objetivos->first()->tipo_equipo_id,
                'valores' => $objetivos
                    ->mapWithKeys(fn (self $objetivo) => [$objetivo->concepto->value => (float) $objetivo->valor])
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the tipo de equipo of a monthly objetivo.
     *
     * @return BelongsTo<TipoEquipo, $this>
     */
    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'modulo' => ModuloObjetivo::class,
            'concepto' => ConceptoObjetivo::class,
            'valor' => 'decimal:2',
        ];
    }
}
