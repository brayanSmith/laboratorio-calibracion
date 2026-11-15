<?php

namespace App\Modules\Recordatorios\Listeners;

use App\Modules\Recordatorios\Models\RecordatorioEnvio;
use Illuminate\Mail\Events\MessageSent;

class MarcarEnvioRecordatorioEnviado
{
    /**
     * Cuando sale un correo del recordatorio (lleva el id de su registro en un encabezado),
     * lo marca como enviado en el historial.
     */
    public function handle(MessageSent $event): void
    {
        $encabezado = $event->message->getHeaders()->get(RecordatorioEnvio::ENCABEZADO);

        if (! $encabezado) {
            return;
        }

        RecordatorioEnvio::query()->find((int) $encabezado->getBodyAsString())?->marcarEnviado();
    }
}
