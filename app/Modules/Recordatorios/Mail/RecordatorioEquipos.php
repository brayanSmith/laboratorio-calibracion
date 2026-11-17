<?php

namespace App\Modules\Recordatorios\Mail;

use App\Models\Empresa;
use App\Modules\Recordatorios\Actions\GenerarPdfRecordatorio;
use App\Modules\Recordatorios\Actions\LimpiarHtmlCorreo;
use App\Modules\Recordatorios\Models\Recordatorio;
use App\Modules\Recordatorios\Models\RecordatorioEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecordatorioEquipos extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $equipos  Filas de ObtenerEquiposRecordatorio, ya filtradas para el destinatario
     * @param  'bahia'|'cliente'  $agruparPor
     * @param  string|null  $plantillaAsunto  Asunto con variables; null usa el de por defecto
     * @param  string|null  $plantillaMensaje  Mensaje con variables; null usa el de por defecto
     */
    public function __construct(
        public int $tenantId,
        public string $destinatario,
        public array $equipos,
        public string $agruparPor,
        public int $diasAnticipacion,
        public ?string $plantillaAsunto = null,
        public ?string $plantillaMensaje = null,
    ) {}

    /**
     * Id del registro en el historial de envíos (RecordatorioEnvio), si se registró.
     */
    public ?int $envioId = null;

    /**
     * Get the message headers: el id del historial, para marcarlo como enviado al salir.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: $this->envioId ? [RecordatorioEnvio::ENCABEZADO => (string) $this->envioId] : [],
        );
    }

    /**
     * Si el correo en cola no se pudo enviar, se anota en el historial.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->envioId) {
            RecordatorioEnvio::query()->find($this->envioId)?->marcarFallido($exception->getMessage());
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $asunto = $this->plantillaAsunto ?? Recordatorio::PLANTILLAS_POR_DEFECTO[$this->agruparPor]['asunto'];

        return new Envelope(
            subject: trim($this->reemplazarVariables($asunto)),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $conteo = collect($this->equipos)->countBy('estado');
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->first();

        return new Content(
            markdown: 'mail.recordatorio',
            with: [
                'empresa' => $empresa->nombre ?? config('app.name'),
                'logo' => $empresa?->logoParaCorreo(),
                'bloques' => $this->bloquesDelMensaje(),
                'vencidos' => $conteo->get('VENCIDO', 0),
                'programados' => $conteo->get('PROGRAMADO', 0),
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

    /**
     * El mensaje del usuario (HTML del editor, limpio) con las variables reemplazadas, en
     * tramos de HTML. Cada null marca dónde va la tabla de conteos ({tabla}).
     *
     * @return list<string|null>
     */
    private function bloquesDelMensaje(): array
    {
        $mensaje = $this->plantillaMensaje ?? Recordatorio::PLANTILLAS_POR_DEFECTO[$this->agruparPor]['mensaje'];

        // Se vuelve a limpiar al enviar, por si el texto llegó a la base de datos sin pasar
        // por el formulario.
        $html = app(LimpiarHtmlCorreo::class)->handle($this->comoHtml($mensaje));
        $html = $this->reemplazarVariables($html, escapar: true);
        // Las imágenes no deben desbordar el ancho del correo.
        $html = str_replace('<img ', '<img style="max-width: 100%; height: auto;" ', $html);
        // {detalle} se reemplaza después de limpiar: es HTML propio, no del usuario.
        $html = preg_replace_callback(
            '#<p>\s*\{detalle\}\s*</p>|\{detalle\}#',
            fn () => $this->tablaDetalle(),
            $html,
        );

        $bloques = [];

        foreach (preg_split('#<p>\s*\{tabla\}\s*</p>|\{tabla\}#', $html) as $indice => $tramo) {
            if ($indice > 0) {
                $bloques[] = null;
            }

            if (trim($tramo) !== '') {
                // En una sola línea, para que el markdown del correo lo deje como HTML.
                $bloques[] = trim(preg_replace('/\R/', ' ', $tramo));
            }
        }

        return $bloques;
    }

    /**
     * Cuántos equipos muestra como máximo la tabla de detalle: el resto queda en el PDF,
     * para que el correo no sea tan largo que el cliente de correo lo corte.
     */
    public const MAXIMO_DETALLE = 50;

    /**
     * La tabla con el detalle de los equipos, como en el PDF (por estado y, en el resumen
     * por bahía, agrupada por bahía), en una sola línea de HTML.
     */
    private function tablaDetalle(): string
    {
        $equipos = collect($this->equipos);
        $mostrados = $equipos->take(self::MAXIMO_DETALLE);

        $html = view('mail.partials.detalle-equipos', [
            'grupos' => $mostrados->groupBy($this->agruparPor === 'bahia' ? 'bahia' : fn () => '')
                ->sortKeys()
                ->map(fn ($filas) => $filas->groupBy('estado')),
            'agruparPor' => $this->agruparPor,
            'faltantes' => $equipos->count() - $mostrados->count(),
        ])->render();

        return trim(preg_replace(['/\R\s*/', '/>\s+</'], [' ', '><'], $html));
    }

    /**
     * Los mensajes guardados antes del editor eran texto plano: se pasan a párrafos.
     */
    private function comoHtml(string $mensaje): string
    {
        if ($mensaje !== strip_tags($mensaje)) {
            return $mensaje;
        }

        return collect(preg_split('/\R\s*\R/', trim($mensaje)))
            ->map(fn (string $parrafo) => '<p>'.collect(preg_split('/\R/', trim($parrafo)))->map(fn (string $linea) => e(trim($linea)))->implode('<br>').'</p>')
            ->implode('');
    }

    /**
     * Reemplaza {nombre}, {empresa}, {vencidos}, etc. Deja {tabla} para el cuerpo. Al
     * reemplazar dentro del HTML, los valores se escapan.
     */
    private function reemplazarVariables(string $texto, bool $escapar = false): string
    {
        $conteo = collect($this->equipos)->countBy('estado');
        $empresa = Empresa::query()->where('tenant_id', $this->tenantId)->value('nombre') ?? config('app.name');

        $valores = [
            '{nombre}' => $this->destinatario,
            '{empresa}' => $empresa,
            '{vencidos}' => (string) $conteo->get('VENCIDO', 0),
            '{programados}' => (string) $conteo->get('PROGRAMADO', 0),
            // Variables anteriores: las plantillas que aún las usan muestran los programados.
            '{proximos}' => (string) $conteo->get('PROGRAMADO', 0),
            '{agendados}' => (string) $conteo->get('PROGRAMADO', 0),
            '{dias}' => (string) $this->diasAnticipacion,
        ];

        return strtr($texto, $escapar ? array_map(e(...), $valores) : $valores);
    }
}
