<?php

namespace App\Models;

use Database\Factories\AjustePlataformaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Identidad pública de la plataforma (nombre y logo de la página de bienvenida).
 *
 * @property int $id
 * @property string|null $nombre
 * @property string|null $logo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nombre', 'logo'])]
class AjustePlataforma extends Model
{
    /** @use HasFactory<AjustePlataformaFactory> */
    use HasFactory;

    /**
     * Get the single settings row, creating it when it does not exist yet.
     */
    public static function actual(): self
    {
        return self::query()->firstOrCreate([]);
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
