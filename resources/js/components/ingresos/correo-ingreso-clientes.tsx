import { router } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    Clock,
    Send,
    SendHorizontal,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import IngresoController from '@/actions/App/Modules/Ingresos/Http/Controllers/IngresoController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatearFechaHora } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import type { Ingreso } from '@/types';

type Destinatario = Ingreso['clientes_correo'][number];

/** Cada cuánto se refresca el estado mientras algún correo sigue en cola. */
const INTERVALO_EN_COLA_MS = 3000;

const ESTADOS = {
    EN_COLA: {
        icono: Clock,
        texto: 'En cola',
        clase: 'text-amber-600 dark:text-amber-400',
    },
    ENVIADO: {
        icono: CircleCheck,
        texto: 'Enviado',
        clase: 'text-emerald-600 dark:text-emerald-400',
    },
    FALLIDO: {
        icono: CircleAlert,
        texto: 'No se pudo enviar',
        clase: 'text-red-600 dark:text-red-400',
    },
} as const;

/**
 * Los clientes a los que les llega el correo del ingreso (los dueños de los equipos
 * recibidos y quien los entregó; a todos con todos los equipos), con el estado del último
 * correo de cada uno (en cola, enviado o por qué falló), un botón para reenviárselo a uno
 * y otro para enviárselo a todos.
 */
export default function CorreoIngresoClientes({
    ingreso,
    puedeReenviar,
}: {
    ingreso: Ingreso;
    puedeReenviar: boolean;
}) {
    const [enviandoA, setEnviandoA] = useState<number | 'todos' | null>(null);

    const visible =
        ingreso.estado_ingreso === 'RECIBIDO' &&
        ingreso.clientes_correo.length > 0;
    const hayEnCola = ingreso.clientes_correo.some(
        (cliente) => cliente.ultimo_envio?.estado === 'EN_COLA',
    );
    const conCorreo = ingreso.clientes_correo.filter(
        (cliente) => cliente.email,
    ).length;

    // Mientras algún correo siga en cola, se refresca hasta que salga o falle.
    useEffect(() => {
        if (!visible || !hayEnCola) {
            return;
        }

        const intervalo = window.setInterval(
            () =>
                router.reload({
                    only: ['ingresos', 'notificacionesSinLeer'],
                    async: true,
                }),
            INTERVALO_EN_COLA_MS,
        );

        return () => window.clearInterval(intervalo);
    }, [visible, hayEnCola]);

    if (!visible) {
        return null;
    }

    const opciones = (clave: number | 'todos') => ({
        preserveScroll: true,
        preserveState: true,
        onStart: () => setEnviandoA(clave),
        onFinish: () => setEnviandoA(null),
    });

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label className="text-xs text-muted-foreground">
                    Correo a los clientes con el código {ingreso.codigo}
                </Label>
                {puedeReenviar && conCorreo > 0 ? (
                    <Button
                        type="button"
                        size="sm"
                        loading={enviandoA === 'todos'}
                        disabled={enviandoA !== null}
                        onClick={() =>
                            router.post(
                                IngresoController.enviarCorreoATodos.url(
                                    ingreso.id,
                                ),
                                {},
                                opciones('todos'),
                            )
                        }
                        data-test="ingreso-correo-enviar-todos"
                    >
                        {enviandoA === 'todos' ? null : <SendHorizontal />}
                        Enviar a todos ({conCorreo})
                    </Button>
                ) : null}
            </div>
            <ul className="divide-y rounded-lg border">
                {ingreso.clientes_correo.map((cliente) => (
                    <li
                        key={cliente.id}
                        className="flex items-start justify-between gap-3 px-3 py-2 text-sm"
                        data-test="ingreso-correo-cliente"
                    >
                        <div className="min-w-0 space-y-0.5">
                            <p className="truncate font-medium">
                                {cliente.nombre}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {cliente.email ?? 'Sin correo registrado'} ·{' '}
                                {cliente.equipos}{' '}
                                {cliente.equipos === 1
                                    ? 'equipo suyo'
                                    : 'equipos suyos'}
                                {cliente.entrego
                                    ? ' · Entregó los equipos'
                                    : ''}
                            </p>
                            <EstadoEnvio cliente={cliente} />
                        </div>
                        {puedeReenviar && cliente.email ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="shrink-0"
                                loading={enviandoA === cliente.id}
                                disabled={enviandoA !== null}
                                onClick={() =>
                                    router.post(
                                        IngresoController.reenviarCorreo.url(
                                            ingreso.id,
                                        ),
                                        { cliente_id: cliente.id },
                                        opciones(cliente.id),
                                    )
                                }
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

/** Cómo terminó el último correo que se le envió al cliente. */
function EstadoEnvio({ cliente }: { cliente: Destinatario }) {
    if (!cliente.ultimo_envio) {
        return cliente.email ? (
            <p className="text-xs text-muted-foreground">Aún no se le envía</p>
        ) : null;
    }

    const estado = ESTADOS[cliente.ultimo_envio.estado];
    const Icono = estado.icono;

    return (
        <div className="space-y-1" data-test="ingreso-correo-estado">
            <p
                className={cn(
                    'flex items-center gap-1 text-xs font-medium',
                    estado.clase,
                )}
            >
                <Icono className="size-3.5" />
                {estado.texto} ·{' '}
                {formatearFechaHora(cliente.ultimo_envio.fecha)}
            </p>
            {cliente.ultimo_envio.estado === 'FALLIDO' &&
            cliente.ultimo_envio.error ? (
                <p className="rounded-md bg-red-50 px-2 py-1 text-xs break-words text-red-700 dark:bg-red-950 dark:text-red-300">
                    {cliente.ultimo_envio.error}
                </p>
            ) : null}
        </div>
    );
}
