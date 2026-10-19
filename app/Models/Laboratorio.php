<?php

namespace App\Models;

use Database\Factories\LaboratorioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $direccion
 * @property bool $principal Solo uno por tenant: es el laboratorio de las calibraciones locales
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'descripcion', 'direccion', 'principal', 'tenant_id'])]
class Laboratorio extends Model
{
    /** @use HasFactory<LaboratorioFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the laboratorio principal of the tenant, if one is marked.
     */
    public static function principalDelTenant(int $tenantId): ?self
    {
        return static::query()->where('tenant_id', $tenantId)->where('principal', true)->first();
    }

    /**
     * Make this the only laboratorio principal of its tenant.
     */
    public function marcarComoPrincipal(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('tenant_id', $this->tenant_id)
                ->whereKeyNot($this->getKey())
                ->where('principal', true)
                ->update(['principal' => false]);

            $this->update(['principal' => true]);
        });
    }

    /**
     * Get the tenant this laboratorio belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the calibraciones done in this laboratorio.
     *
     * @return HasMany<Calibracion, $this>
     */
    public function calibracion(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
        ];
    }
}
