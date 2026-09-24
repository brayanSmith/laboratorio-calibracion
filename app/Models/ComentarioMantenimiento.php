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
 * @property string $comentario
 * @property int $user_id
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Mantenimiento $mantenimiento
 * @property-read User $user
 * @property-read Tenant $tenant
 */
#[Fillable(['mantenimiento_id', 'comentario', 'user_id', 'tenant_id'])]
class ComentarioMantenimiento extends Model
{
    use SoftDeletes;

    /**
     * Get the mantenimiento this comentario belongs to.
     *
     * @return BelongsTo<Mantenimiento, $this>
     */
    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /**
     * Get the user that wrote this comentario.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant this comentario belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
