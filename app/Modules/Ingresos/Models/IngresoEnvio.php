<?php

namespace App\Modules\Ingresos\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Cliente;
use Database\Factories\IngresoEnvioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un correo del ingreso: a quién se le envió, si salió o falló (y por qué) y quién lo
 * disparó, a quien se le avisa en sus notificaciones.
 *
 * @property int $id
 * @property int $ingreso_id
 * @property int|null $cliente_id
 * @property int|null $user_id Quién lo disparó (al recibir el ingreso o al reenviarlo)
 * @property string $destinatario Nombre del cliente
 * @property string $correo
 * @property string $estado EN_COLA, ENVIADO o FALLIDO
 * @property string|null $error Por qué no se pudo enviar
 * @property Carbon|null $enviado_at
 * @property Carbon|null $leido_at Cuándo lo vio en sus notificaciones quien lo disparó
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ingreso $ingreso
 * @property-read Cliente|null $cliente
 * @property-read User|null $user
 * @property-read Tenant $tenant
 */
#[Fillable(['ingreso_id', 'cliente_id', 'user_id', 'destinatario', 'correo', 'estado', 'error', 'enviado_at', 'leido_at', 'tenant_id'])]
#[UseFactory(IngresoEnvioFactory::class)]
class IngresoEnvio extends Model
{
    /** @use HasFactory<IngresoEnvioFactory> */
    use HasFactory;

    /**
     * Encabezado con que el correo lleva el id de su registro, para marcarlo como enviado.
     */
    public const ENCABEZADO = 'X-Ingreso-Envio';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'EN_COLA',
    ];

    /**
     * Marca el correo como enviado.
     */
    public function marcarEnviado(): void
    {
        $this->update(['estado' => 'ENVIADO', 'error' => null, 'enviado_at' => now(), 'leido_at' => null]);
    }

    /**
     * Marca el correo como fallido, con el motivo.
     */
    public function marcarFallido(string $error): void
    {
        $this->update(['estado' => 'FALLIDO', 'error' => mb_substr($error, 0, 2000), 'leido_at' => null]);
    }

    /**
     * Los que quien los disparó aún no ha visto, ya con resultado (los que siguen en cola no
     * cuentan: se avisa cuando salen o fallan).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSinLeer(Builder $query): void
    {
        $query->whereNull('leido_at')->where('estado', '!=', 'EN_COLA');
    }

    /**
     * @return BelongsTo<Ingreso, $this>
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
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
            'enviado_at' => 'datetime',
            'leido_at' => 'datetime',
        ];
    }
}
