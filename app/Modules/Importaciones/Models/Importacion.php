<?php

namespace App\Modules\Importaciones\Models;

use App\Enums\TipoImportacion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property TipoImportacion $tipo
 * @property string $archivo
 * @property string $nombre_original
 * @property string $delimitador
 * @property string $codificacion
 * @property array<string, int> $mapeo campo del sistema => índice de la columna del CSV
 * @property string|null $lote_adjuntos Lote de archivos subidos junto al CSV (ver carpetaAdjuntos())
 * @property string $estado
 * @property int $total_filas
 * @property int $filas_procesadas
 * @property int $filas_creadas
 * @property int $filas_con_error
 * @property list<array{fila: int, mensaje: string}>|null $errores
 * @property Carbon|null $leida_en Cuándo el usuario revisó el resultado
 * @property int $user_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Tenant $tenant
 */
#[Fillable([
    'tipo', 'archivo', 'nombre_original', 'delimitador', 'codificacion', 'mapeo', 'lote_adjuntos', 'estado',
    'total_filas', 'filas_procesadas', 'filas_creadas', 'filas_con_error', 'errores',
    'leida_en', 'user_id', 'tenant_id',
])]
class Importacion extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PROCESANDO = 'PROCESANDO';

    public const ESTADO_COMPLETADA = 'COMPLETADA';

    public const ESTADO_FALLIDA = 'FALLIDA';

    /**
     * Get the user who started this importación.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant this importación belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Carpeta (disco por defecto) donde se guardan los archivos de un lote de adjuntos.
     */
    public static function carpetaDeLote(int $tenantId, string $lote): string
    {
        return "importaciones/{$tenantId}/adjuntos/{$lote}";
    }

    /**
     * Carpeta con los adjuntos subidos junto al CSV, o null si no se subieron.
     */
    public function carpetaAdjuntos(): ?string
    {
        return $this->lote_adjuntos === null ? null : self::carpetaDeLote($this->tenant_id, $this->lote_adjuntos);
    }

    /**
     * Whether the importación is still waiting in the queue or being processed.
     */
    public function enCurso(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_PROCESANDO], true);
    }

    /**
     * Importaciones ya terminadas (bien o con fallo) cuyo resultado el usuario no ha revisado.
     * Las que siguen en curso no cuentan: todavía no hay resultado que leer.
     *
     * @param  Builder<Importacion>  $query
     */
    public function scopeSinLeer(Builder $query): void
    {
        $query->whereIn('estado', [self::ESTADO_COMPLETADA, self::ESTADO_FALLIDA])->whereNull('leida_en');
    }

    /**
     * Summary sent to the frontend to show the progress and the result.
     *
     * @return array{id: int, tipo: string, tipo_etiqueta: string, estado: string, nombre_original: string, creada_en: string|null, leida: bool, total_filas: int, filas_procesadas: int, filas_creadas: int, filas_con_error: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'estado' => $this->estado,
            'nombre_original' => $this->nombre_original,
            'creada_en' => $this->created_at?->toIso8601String(),
            'leida' => $this->leida_en !== null,
            'total_filas' => $this->total_filas,
            'filas_procesadas' => $this->filas_procesadas,
            'filas_creadas' => $this->filas_creadas,
            'filas_con_error' => $this->filas_con_error,
            'errores' => $this->errores ?? [],
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoImportacion::class,
            'mapeo' => 'array',
            'errores' => 'array',
            'leida_en' => 'datetime',
        ];
    }
}
