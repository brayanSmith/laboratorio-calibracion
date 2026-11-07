<?php

namespace App\Models;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use Database\Factories\ObjetivoDashboardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Objetivo anual de un indicador del dashboard (ej. 5 días de tiempo promedio de atención
 * en 2026). El módulo siempre sale del concepto: se completa solo al guardar.
 *
 * @property int $id
 * @property int $anio
 * @property ModuloObjetivo $modulo
 * @property ConceptoObjetivo $concepto
 * @property string $valor
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['anio', 'concepto', 'valor', 'tenant_id'])]
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
            ->get()
            ->groupBy('anio')
            ->map(fn ($objetivos) => $objetivos
                ->mapWithKeys(fn (self $objetivo) => [$objetivo->concepto->value => (float) $objetivo->valor])
                ->all())
            ->all();
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
