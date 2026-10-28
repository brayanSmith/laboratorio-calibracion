<?php

namespace App\Modules\Equipos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una vez en que el equipo se activó o inactivó (ver Equipo::cambiarEstado()).
 *
 * @property int $id
 * @property int $equipo_id
 * @property bool $activo Estado en que quedó el equipo
 * @property string $motivo
 * @property int|null $user_id Quién lo cambió; nulo si fue automático sin usuario (ej. una importación)
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Equipo $equipo
 * @property-read User|null $usuario
 */
#[Fillable(['equipo_id', 'activo', 'motivo', 'user_id', 'tenant_id'])]
class EquipoCambioEstado extends Model
{
    /**
     * Get the equipo whose estado changed.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Get the usuario that changed the estado.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
