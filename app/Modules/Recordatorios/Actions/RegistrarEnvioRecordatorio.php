<?php

namespace App\Modules\Recordatorios\Actions;

use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\RecordatorioEnvio;

class RegistrarEnvioRecordatorio
{
    /**
     * Deja en el historial el correo que se va a enviar (a quién, asunto y equipos) y le
     * pasa al correo el id del registro, para marcarlo como enviado o fallido después.
     *
     * @param  'cliente'|'bahia'|'copia'  $tipo
     */
    public function handle(RecordatorioEquipos $correo, string $direccion, string $tipo, bool $esPrueba = false): RecordatorioEnvio
    {
        $envio = RecordatorioEnvio::query()->create([
            'tipo' => $tipo,
            'es_prueba' => $esPrueba,
            'destinatario' => $correo->destinatario,
            'correo' => $direccion,
            'asunto' => mb_substr((string) $correo->envelope()->subject, 0, 255),
            'equipos' => collect($correo->equipos)
                ->map(fn (array $equipo) => collect($equipo)->only([
                    'codigo', 'tipo_equipo', 'cliente', 'bahia', 'fecha_proximo_servicio', 'estado',
                ])->all())
                ->values()
                ->all(),
            'tenant_id' => $correo->tenantId,
        ]);

        $correo->envioId = $envio->id;

        return $envio;
    }
}
