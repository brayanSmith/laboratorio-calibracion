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
 * @property string $nombre
 * @property string $archivo
 * @property int $equipo_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipo $equipo
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'archivo', 'equipo_id', 'tenant_id'])]
class DocumentoEquipo extends Model
{
    use SoftDeletes;

    /**
     * Get the equipo this documento belongs to.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the tenant this documento belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the public URL of the stored archivo.
     */
    public function archivoUrl(): string
    {
        $path = parse_url(Storage::disk('public')->url($this->archivo), PHP_URL_PATH);

        return is_string($path) ? $path : Storage::disk('public')->url($this->archivo);
    }
}
