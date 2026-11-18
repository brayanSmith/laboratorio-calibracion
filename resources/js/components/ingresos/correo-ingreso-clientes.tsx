import { router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import IngresoController from '@/actions/App/Modules/Ingresos/Http/Controllers/IngresoController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Ingreso } from '@/types';

/**
 * Los clientes a los que les llegó el correo del ingreso (los que tienen equipos
 * recibidos), con un botón para reenviárselo si no lo recibieron.
 */
export default function CorreoIngresoClientes({
    ingreso,
    puedeReenviar,
}: {
    ingreso: Ingreso;
    puedeReenviar: boolean;
}) {
    const [enviandoA, setEnviandoA] = useState<number | null>(null);

    if (
        ingreso.estado_ingreso !== 'RECIBIDO' ||
        ingreso.clientes_correo.length === 0
    ) {
        return null;
    }

    function reenviar(clienteId: number) {
        router.post(
            IngresoController.reenviarCorreo.url(ingreso.id),
            { cliente_id: clienteId },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setEnviandoA(clienteId),
                onFinish: () => setEnviandoA(null),
            },
        );
    }

    return (
        <div className="space-y-2">
            <Label className="text-xs text-muted-foreground">
                Correo al cliente con el código {ingreso.codigo}
            </Label>
            <ul className="divide-y rounded-lg border">
                {ingreso.clientes_correo.map((cliente) => (
                    <li
                        key={cliente.id}
                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                        data-test="ingreso-correo-cliente"
                    >
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {cliente.nombre}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {cliente.email ?? 'Sin correo registrado'} ·{' '}
                                {cliente.equipos}{' '}
                                {cliente.equipos === 1 ? 'equipo' : 'equipos'}
                            </p>
                        </div>
                        {puedeReenviar && cliente.email ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                loading={enviandoA === cliente.id}
                                disabled={enviandoA !== null}
                                onClick={() => reenviar(cliente.id)}
                                data-test="ingreso-correo-reenviar"
                            >
                                {enviandoA === cliente.id ? null : <Send />}
                                {enviandoA === cliente.id
                                    ? 'Enviando…'
                                    : 'Reenviar'}
                            </Button>
                        ) : null}
                    </li>
                ))}
            </ul>
        </div>
    );
}
