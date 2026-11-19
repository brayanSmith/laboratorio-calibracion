<?php

namespace App\Modules\Ingresos\Listeners;

use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Mail\Events\MessageSent;

class MarcarEnvioIngresoEnviado
{
    /**
     * Cuando sale un correo del ingreso (lleva el id de su registro en un encabezado), lo
     * marca como enviado en el historial.
     */
    public function handle(MessageSent $event): void
    {
        $encabezado = $event->message->getHeaders()->get(IngresoEnvio::ENCABEZADO);

        if (! $encabezado) {
            return;
        }

        IngresoEnvio::query()->find((int) $encabezado->getBodyAsString())?->marcarEnviado();
    }
}
