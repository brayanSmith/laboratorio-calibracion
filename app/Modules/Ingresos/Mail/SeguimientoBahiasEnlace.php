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
 * Le envía a un cliente el enlace (firmado y con vencimiento) para ver, sin código, los
 * ingresos y equipos de todas las bahías que tiene asignadas.
 */
class SeguimientoBahiasEnlace extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $bahias  Nombres de las bahías que verá
     */
    public function __construct(
        public int $tenantId,
        public string $cliente,
        public array $bahias,
        public string $enlace,
        public int $diasVigencia,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: Empresa::remitenteCorreo($this->tenantId),
            subject: '[SEGUIMIENTO] Tus equipos por bahía - '.mb_strtoupper($this->cliente),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->first();

        return new Content(
            markdown: 'mail.seguimiento-bahias',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                'acento' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa?->color_tema) ? $empresa->color_tema : '#166534',
            ],
        );
    }
}
