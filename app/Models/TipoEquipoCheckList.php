<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tipo_equipo_id
 * @property string $nombre
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TipoEquipo $tipoEquipo
 * @property-read Tenant $tenant
 */
#[Fillable(['tipo_equipo_id', 'nombre', 'tenant_id'])]
class TipoEquipoCheckList extends Model
{
    use SoftDeletes;

    /**
     * Get the tipo de equipo this check list item belongs to.
     *
     * @return BelongsTo<TipoEquipo, $this>
     */
    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * Get the tenant this check list item belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
