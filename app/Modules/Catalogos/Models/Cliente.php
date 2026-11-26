<?php

namespace App\Modules\Catalogos\Models;

use App\Enums\TipoDocumentoCliente;
use App\Models\Tenant;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Ingresos\Models\Ingreso;
use Database\Factories\ClienteFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * El cliente del laboratorio. También es quien inicia sesión en el portal de clientes
 * (guard "cliente", ver PortalAuthController), con su propio usuario y contraseña, separado
 * de los usuarios del laboratorio y sus permisos.
 *
 * @property int $id
 * @property string $nombre
 * @property TipoDocumentoCliente|null $tipo_documento nulo solo en clientes creados antes de exigirlo
 * @property string|null $numero_documento único por tenant
 * @property string $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property string|null $portal_usuario usuario del portal, único en toda la plataforma
 * @property string|null $portal_password
 * @property bool $portal_activo si el laboratorio le habilitó el acceso al portal
 * @property Carbon|null $portal_ultimo_acceso
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 */
#[Fillable(['nombre', 'tipo_documento', 'numero_documento', 'email', 'telefono', 'direccion', 'tenant_id', 'portal_usuario', 'portal_password', 'portal_activo', 'portal_ultimo_acceso'])]
#[Hidden(['portal_password', 'remember_token'])]
#[UseFactory(ClienteFactory::class)]
class Cliente extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /** @use HasFactory<ClienteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * La contraseña del portal está en su propia columna (el cliente no tiene "password").
     */
    public function getAuthPasswordName(): string
    {
        return 'portal_password';
    }

    /**
     * Si puede entrar al portal: el laboratorio le habilitó el acceso, tiene usuario y
     * contraseña, y su laboratorio sigue activo.
     */
    public function puedeEntrarAlPortal(): bool
    {
        return $this->portal_activo
            && $this->portal_usuario !== null
            && $this->portal_password !== null
            && (bool) $this->tenant?->activo;
    }

    /**
     * Los equipos que el cliente ve en el portal: los suyos y los de las bahías que tiene
     * asignadas, solo de su laboratorio.
     *
     * @return Builder<Equipo>
     */
    public function equiposVisibles(): Builder
    {
        $bahiaIds = $this->bahias()->pluck('bahias.id');

        return Equipo::query()
            ->where('tenant_id', $this->tenant_id)
            ->where(fn (Builder $equipo) => $equipo
                ->where('cliente_id', $this->id)
                ->orWhereIn('bahia_id', $bahiaIds));
    }

    /**
     * Get the tenant this cliente belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the equipos owned by this cliente.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipo(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Get the bahias this cliente is assigned to.
     *
     * @return BelongsToMany<Bahia, $this>
     */
    public function bahias(): BelongsToMany
    {
        return $this->belongsToMany(Bahia::class)->withTimestamps();
    }

    /**
     * Get the despachos where this cliente received the equipos.
     *
     * @return HasMany<Despacho, $this>
     */
    public function despachoRecibido(): HasMany
    {
        return $this->hasMany(Despacho::class, 'cliente_recibe_id');
    }

    /**
     * Get the ingresos where this cliente delivered the equipos.
     *
     * @return HasMany<Ingreso, $this>
     */
    public function ingresoEntregado(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'cliente_entrega_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoCliente::class,
            'portal_password' => 'hashed',
            'portal_activo' => 'boolean',
            'portal_ultimo_acceso' => 'datetime',
        ];
    }
}
