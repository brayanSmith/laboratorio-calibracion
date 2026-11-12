<?php

namespace App\Modules\Recordatorios\Mail;

use App\Models\Empresa;
use App\Modules\Recordatorios\Actions\GenerarPdfRecordatorio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioEquipos extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $equipos  Filas de ObtenerEquiposRecordatorio, ya filtradas para el destinatario
     * @param  'bahia'|'cliente'  $agruparPor
     */
    public function __construct(
        public int $tenantId,
        public string $destinatario,
        public array $equipos,
        public string $agruparPor,
        public int $diasAnticipacion,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->value('nombre');

        return new Envelope(
            subject: trim('Recordatorio de vencimiento de equipos '.($empresa ? "- {$empresa}" : '')),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $conteo = collect($this->equipos)->countBy('estado');

        return new Content(
            markdown: 'mail.recordatorio',
            with: [
                'destinatario' => $this->destinatario,
                'vencidos' => $conteo->get('VENCIDO', 0),
                'proximos' => $conteo->get('PROXIMO_A_VENCER', 0),
                'agendados' => $conteo->get('AGENDADO', 0),
                'diasAnticipacion' => $this->diasAnticipacion,
                'porBahia' => $this->agruparPor === 'bahia',
            ],
        );
    }

    /**
     * El PDF resumen se genera al enviar el correo, para no guardar el binario en la cola.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(GenerarPdfRecordatorio::class)->handle(
                    $this->tenantId,
                    $this->destinatario,
                    $this->equipos,
                    $this->agruparPor,
                    $this->diasAnticipacion,
                ),
                'recordatorio-equipos-'.now()->utc()->format('Y-m-d').'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
