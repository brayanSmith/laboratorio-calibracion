<?php

namespace App\Models;

use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Database\Factories\VersionDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Un PDF emitido de un mantenimiento (informe) o de una calibración (certificado). El
 * original lleva el código base (ej. "CC-000015") y cada corrección le suma una letra
 * ("CC-000015-A", "CC-000015-B", ...); los anteriores se conservan para consultarlos.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property int $numero 0 = original, 1 = -A, 2 = -B, ...
 * @property string $codigo
 * @property string $ruta Ruta del PDF en el disco público
 * @property int|null $user_id Quién hizo el cambio que generó la versión
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Mantenimiento|Calibracion $documentable
 * @property-read User|null $user
 */
#[Fillable(['documentable_type', 'documentable_id', 'numero', 'codigo', 'ruta', 'user_id', 'tenant_id'])]
#[UseFactory(VersionDocumentoFactory::class)]
class VersionDocumento extends Model
{
    /** @use HasFactory<VersionDocumentoFactory> */
    use HasFactory;

    /**
     * Prefijo del código de cada tipo de documento.
     *
     * @var array<class-string, string>
     */
    private const PREFIJOS = [
        Mantenimiento::class => 'IM',
        Calibracion::class => 'CC',
    ];

    /**
     * Código del documento original, ej. "CC-000015" o "IM-000015".
     *
     * @param  class-string  $clase
     */
    public static function codigoBase(string $clase, int $id): string
    {
        return self::PREFIJOS[$clase].'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Letra de la versión, como las columnas de una hoja de cálculo: 1 = A, 26 = Z,
     * 27 = AA. El original (0) no lleva letra.
     */
    public static function letra(int $numero): string
    {
        $letra = '';

        while ($numero > 0) {
            $numero--;
            $letra = chr(65 + $numero % 26).$letra;
            $numero = intdiv($numero, 26);
        }

        return $letra;
    }

    /**
     * Guarda el PDF del documento como una versión: una nueva (la siguiente letra) o, si
     * no, vuelve a escribir la última (cuando se perdió su archivo), y deja su ruta en la
     * columna del documento.
     *
     * @param  callable(string): string  $generarPdf  Recibe el código de la versión y devuelve el PDF
     */
    public static function guardar(Mantenimiento|Calibracion $documento, string $columna, string $carpeta, callable $generarPdf, bool $nueva = true): string
    {
        /** @var self|null $ultima */
        $ultima = $documento->versiones()->reorder('numero', 'desc')->first();

        // Sin tocar updated_at: con él se sabe si el documento cambió después de la última
        // versión (ver Mantenimiento::informeDesactualizado()).
        $registrarRuta = fn (string $ruta) => Model::withoutTimestamps(fn () => $documento->update([$columna => $ruta]));

        if ($ultima && ! $nueva) {
            Storage::disk('public')->put($ultima->ruta, $generarPdf($ultima->codigo));
            $registrarRuta($ultima->ruta);

            return $ultima->ruta;
        }

        $numero = $ultima ? $ultima->numero + 1 : 0;
        $letra = self::letra($numero);
        $codigo = self::codigoBase($documento::class, $documento->id).($letra !== '' ? "-{$letra}" : '');
        $nombre = class_basename($documento).'-'.$documento->id.($letra !== '' ? "-{$letra}" : '');
        $ruta = "{$carpeta}/{$documento->tenant_id}/".strtolower($nombre).'.pdf';

        Storage::disk('public')->put($ruta, $generarPdf($codigo));

        $documento->versiones()->create([
            'numero' => $numero,
            'codigo' => $codigo,
            'ruta' => $ruta,
            'user_id' => Auth::id(),
            'tenant_id' => $documento->tenant_id,
        ]);

        $registrarRuta($ruta);

        return $ruta;
    }

    /**
     * Cómo se lista la versión en la tabla de mantenimientos o calibraciones (necesita
     * cargado user).
     *
     * @return array{id: int, codigo: string, fecha: string|null, usuario: string|null, url: string}
     */
    public function paraListado(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'fecha' => $this->created_at?->toIso8601String(),
            'usuario' => $this->user?->name,
            'url' => route('versiones-documento.show', $this->id),
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
