<?php

namespace App\Modules\Recordatorios\Actions;

use App\Models\User;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatorio
{
    public function __construct(private readonly ObtenerEquiposRecordatorio $obtenerEquipos) {}

    /**
     * Encola los correos del recordatorio: a cada cliente con sus equipos y a cada usuario
     * asignado a bahías con los equipos de sus bahías. Los correos "con copia" reciben el
     * resumen de todos los equipos por bahía. Quien no tenga nada que avisar no recibe correo. Deja anotado el envío para no repetirlo el mismo día.
     *
     * @return int Cantidad de correos encolados
     */
    public function handle(Recordatorio $recordatorio): int
    {
        $equipos = $this->obtenerEquipos->handle($recordatorio->tenant_id, $recordatorio->dias_anticipacion);
        $enviados = 0;

        if ($recordatorio->enviar_a_clientes) {
            $clientes = Cliente::query()
                ->whereIn('id', $equipos->pluck('cliente_id')->unique())
                ->get(['id', 'nombre', 'email']);

            foreach ($clientes as $cliente) {
                if (blank($cliente->email)) {
                    continue;
                }

                Mail::to($cliente->email)->queue(new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $cliente->nombre,
                    $equipos->where('cliente_id', $cliente->id)->values()->all(),
                    'cliente',
                    $recordatorio->dias_anticipacion,
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

                Mail::to($usuario->email)->queue(new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $usuario->name,
                    $deSusBahias->all(),
                    'bahia',
                    $recordatorio->dias_anticipacion,
                ));
                $enviados++;
            }
        }

        if ($equipos->isNotEmpty()) {
            foreach ($recordatorio->correos_copia ?? [] as $correo) {
                Mail::to($correo)->queue(new RecordatorioEquipos(
                    $recordatorio->tenant_id,
                    $correo,
                    $equipos->all(),
                    'bahia',
                    $recordatorio->dias_anticipacion,
                ));
                $enviados++;
            }
        }

        $recordatorio->update(['ultimo_envio_at' => now()]);

        return $enviados;
    }
}
