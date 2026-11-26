<?php

namespace App\Modules\Portal\Mail;

use App\Models\Empresa;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le envía al cliente su usuario y una contraseña del portal: al darle acceso el laboratorio
 * o cuando la olvidó. No va por cola a propósito: así la contraseña no queda guardada en la
 * tabla de trabajos pendientes.
 */
class AccesoPortalCliente extends Mailable
{
    public const MOTIVO_ACCESO = 'acceso';

    public const MOTIVO_RECUPERACION = 'recuperacion';

    public function __construct(
        public Cliente $cliente,
        public string $contrasena,
        public string $motivo = self::MOTIVO_ACCESO,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: Empresa::remitenteCorreo($this->cliente->tenant_id),
            subject: $this->motivo === self::MOTIVO_RECUPERACION
                ? '[PORTAL] Tu nueva contraseña'
                : '[PORTAL] Tu acceso al portal de clientes',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $empresa = Empresa::query()->where('tenant_id', $this->cliente->tenant_id)->first();

        return new Content(
            markdown: 'mail.acceso-portal',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                'acento' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa?->color_tema) ? $empresa->color_tema : '#166534',
                'usuario' => $this->cliente->portal_usuario,
                'enlace' => route('portal.login'),
                'recuperacion' => $this->motivo === self::MOTIVO_RECUPERACION,
            ],
        );
    }
}
