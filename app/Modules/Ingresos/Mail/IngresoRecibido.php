<?php

namespace App\Modules\Ingresos\Mail;

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
 * Le confirma a un cliente qué equipos se recibieron en un ingreso, con el código del
 * ingreso para consultar su estado.
 */
class IngresoRecibido extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{codigo: string, tipo_equipo: string, modelo: string|null, numero_serie: string|null, cliente?: string, area: string, tipo_servicio: string}>  $equipos  Todos los equipos recibidos del ingreso, con su cliente
     * @param  string  $fechaRecepcion  YYYY-MM-DD
     */
    public function __construct(
        public int $tenantId,
        public string $codigo,
        public string $cliente,
        public string $bahia,
        public string $fechaRecepcion,
        public ?string $tecnico,
        public array $equipos,
    ) {}

    /**
     * Id del registro en el historial de envíos (IngresoEnvio), si se registró.
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
            subject: "[INGRESO] Control de equipos N° {$this->codigo} - ".mb_strtoupper($this->cliente),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->first();

        return new Content(
            markdown: 'mail.ingreso-recibido',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                // Mismo acento que la plataforma: el color de tema de la empresa.
                'acento' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa?->color_tema) ? $empresa->color_tema : '#166534',
            ],
        );
    }
}
