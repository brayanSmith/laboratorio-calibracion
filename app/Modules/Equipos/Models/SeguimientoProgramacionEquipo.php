<?php

namespace App\Modules\Equipos\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $programacion_equipo_id
 * @property string $fase BAHIA, INGRESO, MANTENIMIENTO, CALIBRACION o DESPACHO
 * @property string|null $seguimiento Estado de la fase al momento del registro
 * @property string|null $observaciones
 * @property int|null $usuario_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read EquipoProgramacion $programacionEquipo
 * @property-read User|null $usuario
 * @property-read Tenant $tenant
 */
#[Fillable(['programacion_equipo_id', 'fase', 'seguimiento', 'observaciones', 'usuario_id', 'tenant_id'])]
class SeguimientoProgramacionEquipo extends Model
{
    use SoftDeletes;

    /**
     * Get the programacion de equipo this seguimiento belongs to.
     *
     * @return BelongsTo<EquipoProgramacion, $this>
     */
    public function programacionEquipo(): BelongsTo
    {
        return $this->belongsTo(EquipoProgramacion::class, 'programacion_equipo_id');
    }

    /**
     * Get the user who registered this seguimiento.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Get the tenant this seguimiento belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
