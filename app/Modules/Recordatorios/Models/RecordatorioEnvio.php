<?php

namespace App\Modules\Recordatorios\Models;

use App\Models\Tenant;
use Database\Factories\RecordatorioEnvioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un correo del recordatorio: a quién se le envió, con qué asunto y equipos, y si salió.
 *
 * @property int $id
 * @property string $tipo cliente, bahia o copia (los correos "con copia a")
 * @property bool $es_prueba Enviado con "Enviarme una prueba"
 * @property string $destinatario Nombre del cliente o usuario
 * @property string $correo
 * @property string $asunto
 * @property list<array{codigo: string, tipo_equipo: string, cliente: string, bahia: string, fecha_proximo_servicio: string, estado: string}> $equipos
 * @property string $estado EN_COLA, ENVIADO o FALLIDO
 * @property string|null $error Por qué no se pudo enviar
 * @property Carbon|null $enviado_at
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tipo', 'es_prueba', 'destinatario', 'correo', 'asunto', 'equipos', 'estado', 'error', 'enviado_at', 'tenant_id'])]
#[UseFactory(RecordatorioEnvioFactory::class)]
class RecordatorioEnvio extends Model
{
    /** @use HasFactory<RecordatorioEnvioFactory> */
    use HasFactory;

    /**
     * Encabezado con que el correo lleva el id de su registro, para marcarlo como enviado.
     */
    public const ENCABEZADO = 'X-Recordatorio-Envio';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'es_prueba' => false,
        'estado' => 'EN_COLA',
    ];

    /**
     * Marca el correo como enviado.
     */
    public function marcarEnviado(): void
    {
        $this->update(['estado' => 'ENVIADO', 'error' => null, 'enviado_at' => now()]);
    }

    /**
     * Marca el correo como fallido, con el motivo.
     */
    public function marcarFallido(string $error): void
    {
        $this->update(['estado' => 'FALLIDO', 'error' => mb_substr($error, 0, 2000)]);
    }

    /**
     * Get the tenant this envio belongs to.
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
            'es_prueba' => 'boolean',
            'equipos' => 'array',
            'enviado_at' => 'datetime',
        ];
    }
}
