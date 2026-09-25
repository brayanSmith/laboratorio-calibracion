<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $nit
 * @property string $nombre
 * @property string $direccion
 * @property string $telefono
 * @property string|null $logo
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nit', 'nombre', 'direccion', 'telefono', 'logo', 'tenant_id'])]
class Empresa extends Model
{
    use SoftDeletes;

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
     * Get the public path of the logo, independent of the host serving the app.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $path = parse_url(Storage::disk('public')->url($this->logo), PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }
}
