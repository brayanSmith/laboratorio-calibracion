<?php

namespace App\Modules\Portal\Mail;

use App\Models\Empresa;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Portal\Http\Controllers\PortalRegistroController;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le envía al cliente el enlace firmado para terminar de crear su cuenta del portal. No va
 * por cola a propósito: el enlace da acceso a la cuenta y así no queda en la tabla de trabajos.
 */
class RegistroPortalCliente extends Mailable
{
    public function __construct(
        public Cliente $cliente,
        public string $enlace,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: Empresa::remitenteCorreo($this->cliente->tenant_id),
            subject: '[PORTAL] Crea tu cuenta del portal de clientes',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $empresa = Empresa::query()->where('tenant_id', $this->cliente->tenant_id)->first();

        return new Content(
            markdown: 'mail.registro-portal',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                'acento' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa?->color_tema) ? $empresa->color_tema : '#166534',
                'minutos' => PortalRegistroController::MINUTOS_ENLACE,
            ],
        );
    }
}
