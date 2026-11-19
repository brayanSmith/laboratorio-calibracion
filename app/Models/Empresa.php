<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $nit
 * @property string $nombre
 * @property string $direccion
 * @property string $telefono
 * @property string|null $laboratorio_nombre Laboratorio propio: el lugar de las calibraciones locales
 * @property string|null $laboratorio_direccion
 * @property string|null $laboratorio_email
 * @property string|null $laboratorio_telefono
 * @property string|null $logo
 * @property string|null $color_tema
 * @property string|null $color_nav
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable([
    'nit', 'nombre', 'direccion', 'telefono',
    'laboratorio_nombre', 'laboratorio_direccion', 'laboratorio_email', 'laboratorio_telefono',
    'logo', 'color_tema', 'color_nav', 'tenant_id',
])]
class Empresa extends Model
{
    use SoftDeletes;

    /**
     * Get the name of the laboratorio propio of the tenant, where the calibraciones locales are done.
     */
    public static function laboratorioDelTenant(int $tenantId): ?string
    {
        return static::query()->where('tenant_id', $tenantId)->value('laboratorio_nombre');
    }

    /**
     * Get the tenant this empresa belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Remitente de los correos del laboratorio: la dirección configurada (MAIL_FROM_ADDRESS)
     * con el nombre de su empresa, para que el cliente vea quién le escribe. Sin empresa
     * registrada, el nombre configurado.
     */
    public static function remitenteCorreo(int $tenantId): Address
    {
        $nombre = static::query()->where('tenant_id', $tenantId)->value('nombre');

        return new Address(config('mail.from.address'), $nombre ?: config('mail.from.name'));
    }

    /**
     * El contenido del logo, para incrustarlo en un correo: así se ve aunque la app no tenga
     * una URL pública. Se lee el contenido (no la ruta) para que funcione también cuando el
     * disco público es un bucket. Null si no tiene logo o el archivo no existe.
     *
     * @return array{contenido: string, nombre: string, tipo: string|null}|null
     */
    public function logoParaCorreo(): ?array
    {
        $disco = Storage::disk('public');

        if (! $this->logo || ! $disco->exists($this->logo)) {
            return null;
        }

        return [
            'contenido' => $disco->get($this->logo),
            'nombre' => basename($this->logo),
            'tipo' => $disco->mimeType($this->logo) ?: null,
        ];
    }

    /**
     * Get the public path of the logo, independent of the host serving the app.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $url = Storage::disk('public')->url($this->logo);

        if (config('filesystems.disks.public.driver') !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }
}
