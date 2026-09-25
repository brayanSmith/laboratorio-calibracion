<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mantenimiento_id
 * @property int $tipo_equipo_check_list_id
 * @property bool $cumple
 * @property string|null $observacion
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Mantenimiento $mantenimiento
 * @property-read TipoEquipoCheckList $tipoEquipoCheckList
 * @property-read Tenant $tenant
 */
#[Fillable(['mantenimiento_id', 'tipo_equipo_check_list_id', 'cumple', 'observacion', 'tenant_id'])]
class MantenimientoCheckList extends Model
{
    use SoftDeletes;

    /**
     * Get the mantenimiento this check list item belongs to.
     *
     * @return BelongsTo<Mantenimiento, $this>
     */
    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /**
     * Get the tipo de equipo check list item being evaluated.
     *
     * @return BelongsTo<TipoEquipoCheckList, $this>
     */
    public function tipoEquipoCheckList(): BelongsTo
    {
        return $this->belongsTo(TipoEquipoCheckList::class)->withTrashed();
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cumple' => 'boolean',
        ];
    }
}
