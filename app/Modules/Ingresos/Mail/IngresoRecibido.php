<?php

namespace App\Modules\Ingresos\Mail;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Le confirma a un cliente qué equipos suyos se recibieron en un ingreso, con el código del
 * ingreso para consultar su estado.
 */
class IngresoRecibido extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{codigo: string, tipo_equipo: string, modelo: string|null, numero_serie: string|null, area: string, tipo_servicio: string}>  $equipos  Solo los equipos del cliente
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
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
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
