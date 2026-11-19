<?php

namespace App\Modules\Ingresos\Actions;

use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarCorreosIngreso
{
    /**
     * Pone en cola un correo por cada destinatario del ingreso (ver correos()), con todos
     * los equipos recibidos y el código del ingreso, y lo anota en el historial a nombre de
     * quien lo disparó. Los clientes sin correo se omiten.
     *
     * @return int Cuántos correos se pusieron en cola
     */
    public function handle(Ingreso $ingreso, ?int $userId = null): int
    {
        return $this->correos($ingreso)
            ->filter(fn (array $envio) => filled($envio['cliente']->email))
            ->each(function (array $envio) use ($ingreso, $userId): void {
                $envio['correo']->envioId = $this->registrar($ingreso, $envio['cliente'], $userId)->id;

                Mail::to($envio['cliente']->email)->queue($envio['correo']);
            })
            ->count();
    }

    /**
     * Envía de inmediato (sin cola) el correo a un destinatario, anotándolo en el historial
     * como enviado o fallido. Devuelve el registro para saber cómo terminó.
     *
     * @param  array{cliente: Cliente, correo: IngresoRecibido}  $envio
     */
    public function enviarAhora(Ingreso $ingreso, array $envio, ?int $userId = null): IngresoEnvio
    {
        $registro = $this->registrar($ingreso, $envio['cliente'], $userId);

        try {
            Mail::to($envio['cliente']->email)->sendNow($envio['correo']);
            $registro->marcarEnviado();
        } catch (Throwable $exception) {
            report($exception);
            $registro->marcarFallido($exception->getMessage());
        }

        return $registro;
    }

    /**
     * Anota en el historial un correo del ingreso, en cola.
     */
    private function registrar(Ingreso $ingreso, Cliente $cliente, ?int $userId): IngresoEnvio
    {
        return IngresoEnvio::create([
            'ingreso_id' => $ingreso->id,
            'cliente_id' => $cliente->id,
            'user_id' => $userId,
            'destinatario' => $cliente->nombre,
            'correo' => $cliente->email,
            'tenant_id' => $ingreso->tenant_id,
        ]);
    }

    /**
     * El correo de cada destinatario del ingreso (o solo el de $clienteId), indexado por el
     * id del cliente. Destinatarios: los clientes dueños de los equipos recibidos y el
     * cliente que los entregó, que a veces trae equipos de otros clientes. Cada correo lleva
     * todos los equipos recibidos del ingreso, con el cliente de cada uno.
     *
     * @return Collection<int, array{cliente: Cliente, correo: IngresoRecibido}>
     */
    public function correos(Ingreso $ingreso, ?int $clienteId = null): Collection
    {
        $ingreso->loadMissing(['bahia:id,nombre', 'tecnicoRecibe:id,name', 'clienteEntrega']);

        $programaciones = $ingreso->equipoProgramacion()
            ->where('estado_programacion', 'RECIBIDO')
            ->with(['equipo.tipoEquipo', 'equipo.cliente', 'equipo.area'])
            ->get();

        if ($programaciones->isEmpty()) {
            return collect();
        }

        $equipos = $programaciones
            ->sortBy('equipo.codigo')
            ->map(fn (EquipoProgramacion $programacion) => [
                'codigo' => $programacion->equipo->codigo,
                'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
                'modelo' => $programacion->equipo->modelo,
                'numero_serie' => $programacion->equipo->numero_serie,
                'cliente' => $programacion->equipo->cliente->nombre,
                'area' => $programacion->equipo->area->nombre,
                'tipo_servicio' => $programacion->tipoServicioLegible(),
            ])
            ->values()
            ->all();

        return $programaciones
            ->map(fn (EquipoProgramacion $programacion) => $programacion->equipo->cliente)
            ->push($ingreso->clienteEntrega)
            ->filter()
            ->unique('id')
            ->when($clienteId !== null, fn (Collection $clientes) => $clientes->where('id', $clienteId))
            ->keyBy('id')
            ->map(fn (Cliente $cliente): array => [
                'cliente' => $cliente,
                'correo' => new IngresoRecibido(
                    $ingreso->tenant_id,
                    $ingreso->codigo,
                    $cliente->nombre,
                    $ingreso->bahia->nombre,
                    ($ingreso->fecha_recepcion ?? now())->toDateString(),
                    $ingreso->tecnicoRecibe?->name,
                    $equipos,
                ),
            ]);
    }
}
