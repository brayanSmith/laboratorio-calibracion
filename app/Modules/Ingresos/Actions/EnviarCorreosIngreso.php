<?php

namespace App\Modules\Ingresos\Actions;

use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class EnviarCorreosIngreso
{
    /**
     * Pone en cola un correo por cada cliente con equipos recibidos en el ingreso, solo con
     * sus equipos y el código del ingreso. Los clientes sin correo registrado se omiten.
     *
     * @return int Cuántos correos se pusieron en cola
     */
    public function handle(Ingreso $ingreso): int
    {
        return $this->correos($ingreso)
            ->filter(fn (array $envio) => filled($envio['cliente']->email))
            ->each(fn (array $envio) => Mail::to($envio['cliente']->email)->queue($envio['correo']))
            ->count();
    }

    /**
     * El correo de cada cliente con equipos recibidos en el ingreso (o solo el de
     * $clienteId), indexado por el id del cliente.
     *
     * @return Collection<int, array{cliente: Cliente, correo: IngresoRecibido}>
     */
    public function correos(Ingreso $ingreso, ?int $clienteId = null): Collection
    {
        $ingreso->loadMissing(['bahia:id,nombre', 'tecnicoRecibe:id,name']);

        return $ingreso->equipoProgramacion()
            ->where('estado_programacion', 'RECIBIDO')
            ->when($clienteId !== null, fn ($query) => $query->whereHas('equipo', fn ($equipo) => $equipo->where('cliente_id', $clienteId)))
            ->with(['equipo.tipoEquipo', 'equipo.cliente', 'equipo.area'])
            ->get()
            ->groupBy('equipo.cliente_id')
            ->map(function (Collection $programaciones) use ($ingreso): array {
                $cliente = $programaciones->first()->equipo->cliente;

                return [
                    'cliente' => $cliente,
                    'correo' => new IngresoRecibido(
                        $ingreso->tenant_id,
                        $ingreso->codigo,
                        $cliente->nombre,
                        $ingreso->bahia->nombre,
                        ($ingreso->fecha_recepcion ?? now())->toDateString(),
                        $ingreso->tecnicoRecibe?->name,
                        $programaciones
                            ->sortBy('equipo.codigo')
                            ->map(fn (EquipoProgramacion $programacion) => [
                                'codigo' => $programacion->equipo->codigo,
                                'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
                                'modelo' => $programacion->equipo->modelo,
                                'numero_serie' => $programacion->equipo->numero_serie,
                                'area' => $programacion->equipo->area->nombre,
                                'tipo_servicio' => $programacion->tipoServicioLegible(),
                            ])
                            ->values()
                            ->all(),
                    ),
                ];
            });
    }
}
