<?php

namespace App\Modules\Despachos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una vez en que cambió la fecha programada de un despacho (ver Despacho::reagendar()).
 *
 * @property int $id
 * @property int $despacho_id
 * @property Carbon $fecha_anterior
 * @property Carbon $fecha_nueva
 * @property string $motivo
 * @property int|null $user_id Quién lo re-agendó
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Despacho $despacho
 * @property-read User|null $usuario
 */
#[Fillable(['despacho_id', 'fecha_anterior', 'fecha_nueva', 'motivo', 'user_id', 'tenant_id'])]
class DespachoReagendamiento extends Model
{
    /**
     * Get the despacho that was re-agendado.
     *
     * @return BelongsTo<Despacho, $this>
     */
    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }

    /**
     * Get the usuario that re-agendó the despacho.
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
            'fecha_anterior' => 'date',
            'fecha_nueva' => 'date',
        ];
    }
}
