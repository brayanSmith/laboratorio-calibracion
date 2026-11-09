<?php

namespace App\Models;

use App\Modules\Objetivos\Policies\ObjetivoUserPolicy;
use Database\Factories\ObjetivoUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Objetivo de un usuario (cantidad de mantenimientos o calibraciones) para un año completo
 * (sin mes) o para un mes del año.
 *
 * @property int $id
 * @property int $user_id
 * @property int $anio
 * @property int|null $mes Nulo cuando el objetivo es de todo el año
 * @property string $tipo MANTENIMIENTO o CALIBRACION
 * @property string $valor
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Tenant $tenant
 */
#[Fillable(['user_id', 'anio', 'mes', 'tipo', 'valor', 'tenant_id'])]
#[UsePolicy(ObjetivoUserPolicy::class)]
class ObjetivoUser extends Model
{
    /** @use HasFactory<ObjetivoUserFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Tipos de servicio que puede medir un objetivo.
     *
     * @var list<string>
     */
    public const TIPOS = ['MANTENIMIENTO', 'CALIBRACION'];

    /**
     * Cantidad que deben hacer los usuarios dados (sin usuarios, todos los del tenant) en
     * cada mes de los años dados, sumada por mes y tipo de servicio.
     *
     * @param  list<int>  $anios
     * @param  list<int>  $usuarios
     * @return list<array{anio: int, mes: int, tipo: string, valor: float}>
     */
    public static function sumaPorMes(int $tenantId, array $anios, array $usuarios = []): array
    {
        return self::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('anio', $anios)
            ->whereNotNull('mes')
            ->when($usuarios !== [], fn ($query) => $query->whereIn('user_id', $usuarios))
            ->groupBy('anio', 'mes', 'tipo')
            ->orderBy('anio')
            ->orderBy('mes')
            ->selectRaw('anio, mes, tipo, sum(valor) as total')
            ->toBase()
            ->get()
            ->map(fn (object $fila): array => [
                'anio' => (int) $fila->anio,
                'mes' => (int) $fila->mes,
                'tipo' => (string) $fila->tipo,
                'valor' => round((float) $fila->total, 2),
            ])
            ->all();
    }

    /**
     * Get the user this objetivo belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'valor' => 'decimal:2',
        ];
    }
}
