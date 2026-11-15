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
 * @property string $mes_aviso ACTUAL o SIGUIENTE: el mes que avisa el envío MENSUAL
 * @property string $hora_envio HH:MM en UTC
 * @property int $dias_anticipacion Días hacia adelante que cuentan como "próximo a vencer", si es SEMANAL
 * @property bool $enviar_a_clientes Cada cliente recibe un PDF con sus equipos
 * @property bool $enviar_resumen_bahia Cada usuario asignado a bahías recibe un PDF con los equipos de sus bahías
 * @property list<string>|null $correos_copia Correos adicionales que reciben el resumen de todas las bahías
 * @property string|null $asunto_cliente Asunto del correo a clientes; null usa el de por defecto
 * @property string|null $mensaje_cliente Cuerpo del correo a clientes; null usa el de por defecto
 * @property string|null $asunto_bahia Asunto del resumen por bahía (y de los correos con copia)
 * @property string|null $mensaje_bahia Cuerpo del resumen por bahía (y de los correos con copia)
 * @property Carbon|null $ultimo_envio_at
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $plantilla_cliente_id Plantilla en uso del correo a clientes; null usa la predeterminada
 * @property int|null $plantilla_bahia_id Plantilla en uso del resumen por bahía; null usa la predeterminada
 * @property-read Tenant $tenant
 * @property-read RecordatorioPlantilla|null $plantillaCliente
 * @property-read RecordatorioPlantilla|null $plantillaBahia
 */
#[Fillable([
    'activo', 'frecuencia', 'dia_semana', 'dia_mes', 'mes_aviso', 'hora_envio', 'dias_anticipacion',
    'enviar_a_clientes', 'enviar_resumen_bahia', 'correos_copia',
    'asunto_cliente', 'mensaje_cliente', 'asunto_bahia', 'mensaje_bahia',
    'plantilla_cliente_id', 'plantilla_bahia_id', 'ultimo_envio_at', 'tenant_id',
])]
class Recordatorio extends Model
{
    /**
     * @var list<string>
     */
    public const FRECUENCIAS = ['SEMANAL', 'MENSUAL'];

    /**
     * El mes que avisa el envío mensual: el mismo en que sale o el siguiente (para
     * enviarlo unos días antes de que empiece).
     *
     * @var list<string>
     */
    public const MESES_AVISO = ['ACTUAL', 'SIGUIENTE'];

    /**
     * Variables que se pueden usar en el asunto y el mensaje, con lo que significan.
     * {tabla} solo aplica al mensaje: ahí va la tabla con los conteos.
     *
     * @var array<string, string>
     */
    public const VARIABLES = [
        '{nombre}' => 'Nombre del destinatario',
        '{empresa}' => 'Nombre de tu empresa',
        '{vencidos}' => 'Cantidad de equipos vencidos',
        '{proximos}' => 'Cantidad de equipos próximos a vencer',
        '{agendados}' => 'Cantidad de equipos agendados',
        '{dias}' => 'Días de anticipación configurados',
        '{tabla}' => 'Tabla con los conteos (solo en el mensaje)',
        '{detalle}' => 'Tabla con el detalle de los equipos, como en el PDF (solo en el mensaje)',
    ];

    /**
     * Textos que se envían mientras el tenant no personalice los suyos. El mensaje es HTML
     * como el que guarda el editor (ya limpio), para poder compararlo con lo enviado.
     *
     * @var array{cliente: array{asunto: string, mensaje: string}, bahia: array{asunto: string, mensaje: string}}
     */
    public const PLANTILLAS_POR_DEFECTO = [
        'cliente' => [
            'asunto' => 'Recordatorio de vencimiento de equipos - {empresa}',
            'mensaje' => '<p>Hola, {nombre}:</p><p>Te enviamos el resumen de tus equipos que requieren atención: los vencidos y los que vencen en los próximos {dias} días.</p><p>{tabla}</p><p>Encontrarás el detalle de cada equipo en el PDF adjunto.</p><p>Saludos,<br />{empresa}</p>',
        ],
        'bahia' => [
            'asunto' => 'Resumen de equipos por bahía - {empresa}',
            'mensaje' => '<p>Hola, {nombre}:</p><p>Te enviamos el resumen de los equipos de tus bahías que requieren atención: los vencidos y los que vencen en los próximos {dias} días.</p><p>{tabla}</p><p>Encontrarás el detalle de cada equipo, agrupado por bahía, en el PDF adjunto.</p><p>Saludos,<br />{empresa}</p>',
        ],
    ];

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
        'mes_aviso' => 'ACTUAL',
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
     * Hasta qué día avisa un envío hecho ese día (en UTC): semanal, los días de
     * anticipación configurados; mensual, hasta el último día del mes elegido, para que
     * no se escape ninguno sin importar cuántos días tenga el mes.
     */
    public function fechaLimiteAviso(?CarbonInterface $hoy = null): CarbonInterface
    {
        $hoy = ($hoy ?? now())->toImmutable()->utc()->startOfDay();

        if ($this->frecuencia !== 'MENSUAL') {
            return $hoy->addDays($this->dias_anticipacion);
        }

        $mes = $this->mes_aviso === 'SIGUIENTE' ? $hoy->addMonthNoOverflow() : $hoy;

        return $mes->endOfMonth()->startOfDay();
    }

    /**
     * Desde qué día avisa lo que está por vencer un envío hecho ese día (los vencidos van
     * siempre): el mensual del mes siguiente arranca el 1 de ese mes, porque lo que vence
     * de aquí a fin de mes ya lo avisó el envío del mes pasado; los demás, desde hoy.
     */
    public function fechaInicioAviso(?CarbonInterface $hoy = null): CarbonInterface
    {
        $hoy = ($hoy ?? now())->toImmutable()->utc()->startOfDay();

        return $this->frecuencia === 'MENSUAL' && $this->mes_aviso === 'SIGUIENTE'
            ? $hoy->addMonthNoOverflow()->startOfMonth()
            : $hoy;
    }

    /**
     * Cuántos días faltan para fechaInicioAviso().
     */
    public function diasHastaInicioAviso(?CarbonInterface $hoy = null): int
    {
        $hoy = ($hoy ?? now())->toImmutable()->utc()->startOfDay();

        return (int) $hoy->diffInDays($this->fechaInicioAviso($hoy));
    }

    /**
     * Cuántos días hacia adelante avisa un envío hecho ese día: hasta fechaLimiteAviso().
     */
    public function diasAviso(?CarbonInterface $hoy = null): int
    {
        $hoy = ($hoy ?? now())->toImmutable()->utc()->startOfDay();

        return (int) $hoy->diffInDays($this->fechaLimiteAviso($hoy));
    }

    /**
     * El asunto y el mensaje con que sale el correo de ese tipo: los de la plantilla en uso
     * (ej. "Navidad") o, si no hay, los de la predeterminada.
     *
     * @param  'cliente'|'bahia'  $tipo
     * @return array{asunto: string, mensaje: string}
     */
    public function plantilla(string $tipo): array
    {
        $enUso = $tipo === 'cliente' ? $this->plantillaCliente : $this->plantillaBahia;

        if ($enUso) {
            return ['asunto' => $enUso->asunto, 'mensaje' => $enUso->mensaje];
        }

        return $this->plantillaPredeterminada($tipo);
    }

    /**
     * El asunto y el mensaje de la plantilla predeterminada de ese tipo: los personalizados
     * por el tenant o, si no los cambió, los de por defecto.
     *
     * @param  'cliente'|'bahia'  $tipo
     * @return array{asunto: string, mensaje: string}
     */
    public function plantillaPredeterminada(string $tipo): array
    {
        $porDefecto = self::PLANTILLAS_POR_DEFECTO[$tipo];

        return [
            'asunto' => $this->getAttribute("asunto_{$tipo}") ?? $porDefecto['asunto'],
            'mensaje' => $this->getAttribute("mensaje_{$tipo}") ?? $porDefecto['mensaje'],
        ];
    }

    /**
     * La plantilla en uso del correo a clientes; null si es la predeterminada.
     *
     * @return BelongsTo<RecordatorioPlantilla, $this>
     */
    public function plantillaCliente(): BelongsTo
    {
        return $this->belongsTo(RecordatorioPlantilla::class, 'plantilla_cliente_id');
    }

    /**
     * La plantilla en uso del resumen por bahía; null si es la predeterminada.
     *
     * @return BelongsTo<RecordatorioPlantilla, $this>
     */
    public function plantillaBahia(): BelongsTo
    {
        return $this->belongsTo(RecordatorioPlantilla::class, 'plantilla_bahia_id');
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
