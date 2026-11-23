<?php

namespace App\Modules\Equipos\Mail;

use App\Models\Empresa;
use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Le avisa al cliente que uno de sus equipos quedó inhabilitado (fuera de servicio), con
 * el motivo, el detalle si lo hay, cuándo y quién lo registró.
 */
class EquipoInhabilitado extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{codigo: string, tipo_equipo: string, modelo: string|null, numero_serie: string|null, area: string|null, bahia: string|null}  $equipo
     * @param  string  $fecha  Fecha y hora en que quedó inhabilitado, ya formateada
     */
    public function __construct(
        public int $tenantId,
        public string $cliente,
        public array $equipo,
        public string $motivo,
        public ?string $detalle,
        public string $fecha,
        public ?string $registradoPor,
    ) {
        // Si se inhabilita dentro de una transacción, el correo sale solo si esta se confirma.
        $this->afterCommit();
    }

    /**
     * Id del registro en el historial de la campanita (IngresoEnvio), si se registró.
     */
    public ?int $envioId = null;

    /**
     * Get the message headers: el id del historial, para marcarlo como enviado al salir.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: $this->envioId ? [IngresoEnvio::ENCABEZADO => (string) $this->envioId] : [],
        );
    }

    /**
     * Si el correo en cola no se pudo enviar, se anota en el historial.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->envioId) {
            IngresoEnvio::query()->find($this->envioId)?->marcarFallido($exception->getMessage());
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: Empresa::remitenteCorreo($this->tenantId),
            subject: "[EQUIPO INHABILITADO] {$this->equipo['codigo']} - {$this->equipo['tipo_equipo']} - ".mb_strtoupper($this->cliente),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->first();

        return new Content(
            markdown: 'mail.equipo-inhabilitado',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                // Mismo acento que la plataforma: el color de tema de la empresa.
                'acento' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa?->color_tema) ? $empresa->color_tema : '#166534',
            ],
        );
    }
}
