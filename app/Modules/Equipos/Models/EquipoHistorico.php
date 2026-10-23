<?php

namespace App\Modules\Equipos\Models;

use App\Models\Tenant;
use Database\Factories\EquipoHistoricoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio que se le hizo al equipo antes de usar el sistema (o en otro sistema), importado
 * desde un CSV con los datos mínimos comunes: cuándo, qué servicio, dónde y su respaldo.
 *
 * @property int $id
 * @property int $equipo_id
 * @property string $tipo_servicio MANTENIMIENTO o CALIBRACION
 * @property Carbon $fecha_servicio
 * @property string $nombre_laboratorio
 * @property bool $es_tercero
 * @property string|null $adjunto Ruta del archivo en el disco público
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read Tenant $tenant
 */
#[Fillable(['equipo_id', 'tipo_servicio', 'fecha_servicio', 'nombre_laboratorio', 'es_tercero', 'adjunto', 'tenant_id'])]
#[UseFactory(EquipoHistoricoFactory::class)]
class EquipoHistorico extends Model
{
    /** @use HasFactory<EquipoHistoricoFactory> */
    use HasFactory, SoftDeletes;

    public const TIPOS_SERVICIO = ['MANTENIMIENTO', 'CALIBRACION'];

    /** Extensiones aceptadas como adjunto al agregarlo a mano. */
    public const EXTENSIONES_ADJUNTO = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** Tamaño máximo del adjunto, en KB. */
    public const TAMANIO_MAXIMO_ADJUNTO_KB = 20480;

    /**
     * Carpeta (disco público) donde se guardan los adjuntos del histórico de un equipo.
     */
    public static function carpetaAdjuntos(int $tenantId, int $equipoId): string
    {
        return "historicos/{$tenantId}/{$equipoId}";
    }

    /**
     * Nombre con el que se guarda un adjunto: el original con un prefijo al azar, para no
     * pisar otro archivo con el mismo nombre (adjuntoNombre() lo quita).
     */
    public static function nombreArchivoAdjunto(string $nombreOriginal): string
    {
        return Str::random(8).'-'.basename(str_replace('\\', '/', $nombreOriginal));
    }

    /**
     * Datos que se muestran en la pestaña Histórico del historial del equipo.
     *
     * @return array{id: int, tipo_servicio: string, fecha_servicio: string, nombre_laboratorio: string, es_tercero: bool, adjunto_url: string|null, adjunto_nombre: string|null}
     */
    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'tipo_servicio' => $this->tipo_servicio,
            'fecha_servicio' => $this->fecha_servicio->toDateString(),
            'nombre_laboratorio' => $this->nombre_laboratorio,
            'es_tercero' => $this->es_tercero,
            'adjunto_url' => $this->adjuntoUrl(),
            'adjunto_nombre' => $this->adjuntoNombre(),
        ];
    }

    /**
     * Get the equipo this histórico belongs to.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the tenant this histórico belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the public URL of the adjunto, if it has one.
     */
    public function adjuntoUrl(): ?string
    {
        if (! $this->adjunto) {
            return null;
        }

        $url = Storage::disk('public')->url($this->adjunto);

        if (config('filesystems.disks.public.driver') !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $url;
    }

    /**
     * Get the original name of the adjunto, without the prefix added when it was stored.
     */
    public function adjuntoNombre(): ?string
    {
        return $this->adjunto === null ? null : Str::after(basename($this->adjunto), '-');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_servicio' => 'date',
            'es_tercero' => 'boolean',
        ];
    }
}
