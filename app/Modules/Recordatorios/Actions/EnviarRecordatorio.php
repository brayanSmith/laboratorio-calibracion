<?php

namespace App\Modules\Recordatorios\Actions;

use App\Models\User;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarRecordatorio
{
    public function __construct(
        private readonly ObtenerEquiposRecordatorio $obtenerEquipos,
        private readonly RegistrarEnvioRecordatorio $registrarEnvio,
    ) {}

    /**
     * Encola los correos del recordatorio: a cada cliente con sus equipos y a cada usuario
     * asignado a bahías con los equipos de sus bahías. Los correos "con copia" reciben el
     * resumen de todos los equipos por bahía. Quien no tenga nada que avisar no recibe correo.
     * Cada correo queda en el historial de envíos. Deja anotado el envío para no repetirlo el
     * mismo día.
     *
     * @return int Cantidad de correos encolados
     */
    public function handle(Recordatorio $recordatorio): int
    {
        $diasAviso = $recordatorio->diasAviso();
        $equipos = $this->obtenerEquipos->handle($recordatorio->tenant_id, $diasAviso, $recordatorio->diasHastaInicioAviso());
        $plantillaCliente = $recordatorio->plantilla('cliente');
        $plantillaBahia = $recordatorio->plantilla('bahia');
        $enviados = 0;

        if ($recordatorio->enviar_a_clientes) {
            $clientes = Cliente::query()
                ->whereIn('id', $equipos->pluck('cliente_id')->unique())
                ->get(['id', 'nombre', 'email']);

            foreach ($clientes as $cliente) {
                if (blank($cliente->email)) {
                    continue;
                }

                $this->encolar($cliente->email, 'cliente', new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $cliente->nombre,
                    $equipos->where('cliente_id', $cliente->id)->values()->all(),
                    'cliente',
                    $diasAviso,
                    $plantillaCliente['asunto'],
                    $plantillaCliente['mensaje'],
                ));
                $enviados++;
            }
        }

        if ($recordatorio->enviar_resumen_bahia) {
            $usuarios = User::query()
                ->where('tenant_id', $recordatorio->tenant_id)
                ->whereHas('bahias')
                ->with('bahias:id')
                ->get(['id', 'name', 'email']);

            foreach ($usuarios as $usuario) {
                $deSusBahias = $equipos->whereIn('bahia_id', $usuario->bahias->pluck('id'))->values();

                if ($deSusBahias->isEmpty()) {
                    continue;
                }

                $this->encolar($usuario->email, 'bahia', new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $usuario->name,
                    $deSusBahias->all(),
                    'bahia',
                    $diasAviso,
                    $plantillaBahia['asunto'],
                    $plantillaBahia['mensaje'],
                ));
                $enviados++;
            }
        }

        if ($equipos->isNotEmpty()) {
            foreach ($recordatorio->correos_copia ?? [] as $correo) {
                $this->encolar($correo, 'copia', new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $correo,
                    $equipos->all(),
                    'bahia',
                    $diasAviso,
                    $plantillaBahia['asunto'],
                    $plantillaBahia['mensaje'],
                ));
                $enviados++;
            }
        }

        $recordatorio->update(['ultimo_envio_at' => now()]);

        return $enviados;
    }

    /**
     * Deja el correo en el historial y lo encola. Si ni siquiera se pudo encolar, queda como
     * fallido en el historial.
     *
     * @param  'cliente'|'bahia'|'copia'  $tipo
     */
    private function encolar(string $direccion, string $tipo, RecordatorioEquipos $correo): void
    {
        $envio = $this->registrarEnvio->handle($correo, $direccion, $tipo);

        try {
            Mail::to($direccion)->queue($correo);
        } catch (Throwable $exception) {
            $envio->marcarFallido($exception->getMessage());

            throw $exception;
        }
    }
}
