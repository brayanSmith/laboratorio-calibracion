<?php

namespace App\Modules\Recordatorios\Models;

use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $activo
 * @property string $frecuencia SEMANAL o MENSUAL
 * @property int|null $dia_semana 1 = lunes ... 7 = domingo (ISO), si es SEMANAL
 * @property int|null $dia_mes 1 a 28, si es MENSUAL
 * @property string $hora_envio HH:MM en UTC
 * @property int $dias_anticipacion Días hacia adelante que cuentan como "próximo a vencer"
 * @property bool $enviar_a_clientes Cada cliente recibe un PDF con sus equipos
 * @property bool $enviar_resumen_bahia Cada usuario asignado a bahías recibe un PDF con los equipos de sus bahías
 * @property list<string>|null $correos_copia Correos adicionales que reciben el resumen de todas las bahías
 * @property Carbon|null $ultimo_envio_at
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable([
    'activo', 'frecuencia', 'dia_semana', 'dia_mes', 'hora_envio', 'dias_anticipacion',
    'enviar_a_clientes', 'enviar_resumen_bahia', 'correos_copia', 'ultimo_envio_at', 'tenant_id',
])]
class Recordatorio extends Model
{
    /**
     * @var list<string>
     */
    public const FRECUENCIAS = ['SEMANAL', 'MENSUAL'];

    /**
     * Same defaults as the table, so the configuration of a tenant that never saved one
     * can be shown with them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => false,
        'frecuencia' => 'SEMANAL',
        'dia_semana' => 1,
        'hora_envio' => '08:00',
        'dias_anticipacion' => 30,
        'enviar_a_clientes' => true,
        'enviar_resumen_bahia' => true,
    ];

    /**
     * Si toca enviar el recordatorio en ese momento (en UTC): está activo, es el día elegido
     * de la semana o del mes, ya pasó la hora de envío y aún no se envió ese día. Comparar
     * "ya pasó la hora" en vez de "es la hora exacta" hace que, si el scheduler se salta un
     * minuto, el envío salga igual en la siguiente pasada.
     */
    public function debeEnviarse(CarbonInterface $momento): bool
    {
        $momento = $momento->utc();

        if (! $this->activo) {
            return false;
        }

        $esElDia = $this->frecuencia === 'MENSUAL'
            ? $momento->day === $this->dia_mes
            : $momento->dayOfWeekIso === $this->dia_semana;

        if (! $esElDia || $momento->format('H:i') < $this->hora_envio) {
            return false;
        }

        return $this->ultimo_envio_at === null || ! $this->ultimo_envio_at->utc()->isSameDay($momento);
    }

    /**
     * Get the tenant this recordatorio belongs to.
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
            'activo' => 'boolean',
            'dia_semana' => 'integer',
            'dia_mes' => 'integer',
            'dias_anticipacion' => 'integer',
            'enviar_a_clientes' => 'boolean',
            'enviar_resumen_bahia' => 'boolean',
            'correos_copia' => 'array',
            'ultimo_envio_at' => 'datetime',
        ];
    }
}
