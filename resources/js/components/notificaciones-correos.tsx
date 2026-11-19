import { Link, router, useHttp, usePage } from '@inertiajs/react';
import { Bell, CircleAlert, CircleCheck, Clock, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    index as listarNotificaciones,
    marcarLeidas,
} from '@/actions/App/Modules/Ingresos/Http/Controllers/NotificacionCorreoController';
import BotonAtajo from '@/components/boton-atajo';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { formatearFechaHora } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import { index as ingresos } from '@/routes/ingresos';

/** Cómo terminó un correo del ingreso que disparó el usuario. */
export type NotificacionCorreo = {
    id: number;
    ingreso_id: number;
    ingreso_codigo: string | null;
    destinatario: string;
    correo: string;
    estado: 'EN_COLA' | 'ENVIADO' | 'FALLIDO';
    error: string | null;
    fecha: string;
    leido: boolean;
};

/** Cada cuánto se refresca la lista mientras hay correos en cola y está abierta. */
const INTERVALO_EN_COLA_MS = 3000;

/** Cada cuánto se revisa el globito con la campanita cerrada. */
const INTERVALO_CONTEO_MS = 30000;

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
 * Campanita del menú superior (y de la barra inferior en móvil): cómo terminaron los correos
 * del ingreso que disparó el usuario, al estilo de las notificaciones de las redes
 * sociales. El globito cuenta los que salieron o fallaron y aún no ve; al abrirla se
 * marcan como vistos. Si uno falló, muestra el motivo.
 */
export default function NotificacionesCorreos() {
    const { notificacionesSinLeer } = usePage().props;
    const [abierta, setAbierta] = useState(false);
    const [notificaciones, setNotificaciones] = useState<
        NotificacionCorreo[] | null
    >(null);
    const [actualizando, setActualizando] = useState(false);
    const { get } = useHttp<
        Record<string, never>,
        { notificaciones: NotificacionCorreo[] }
    >();
    const marcar = useHttp<Record<string, never>, { sin_leer: number }>();

    const sinLeer = notificacionesSinLeer ?? 0;
    const hayEnCola =
        notificaciones?.some((item) => item.estado === 'EN_COLA') ?? false;

    const cargar = () =>
        get(listarNotificaciones().url)
            .then((respuesta) => setNotificaciones(respuesta.notificaciones))
            .catch(() => setNotificaciones((actuales) => actuales ?? []));

    // Al abrir: trae la lista y marca como vistas las que ya tienen resultado.
    useEffect(() => {
        if (!abierta) {
            return;
        }

        void cargar().then(() => {
            if (sinLeer > 0) {
                void marcar
                    .patch(marcarLeidas().url)
                    .then(() =>
                        router.reload({
                            only: ['notificacionesSinLeer'],
                            async: true,
                        }),
                    )
                    .catch(() => undefined);
            }
        });
        // Solo al abrirla.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abierta]);

    // Abierta y con correos en cola: se refresca hasta que salgan o fallen.
    useEffect(() => {
        if (!abierta || !hayEnCola) {
            return;
        }

        const intervalo = window.setInterval(
            () => void cargar(),
            INTERVALO_EN_COLA_MS,
        );

        return () => window.clearInterval(intervalo);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abierta, hayEnCola]);

    // Cerrada: revisa de vez en cuando si llegó algo nuevo, para el globito.
    useEffect(() => {
        if (abierta) {
            return;
        }

        const intervalo = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['notificacionesSinLeer'], async: true });
            }
        }, INTERVALO_CONTEO_MS);

        return () => window.clearInterval(intervalo);
    }, [abierta]);

    if (notificacionesSinLeer === null || notificacionesSinLeer === undefined) {
        return null;
    }

    return (
        <Popover open={abierta} onOpenChange={setAbierta}>
            <BotonAtajo
                icono={Bell}
                titulo="Notificaciones"
                etiqueta="Avisos"
                activo={abierta}
                cantidad={sinLeer}
                dataTest="notificaciones-button"
                onClick={() => setAbierta((actual) => !actual)}
            >
                {/* El ancla cubre la campana: la ventana se abre justo debajo de ella
                    (o encima, en la barra inferior del móvil). */}
                <PopoverAnchor className="pointer-events-none absolute inset-0" />
            </BotonAtajo>
            <PopoverContent
                side="bottom"
                align="end"
                sideOffset={10}
                collisionPadding={12}
                className="w-[min(24rem,calc(100vw-1.5rem))] p-0"
                // Tocar la campana con la ventana abierta la cierra (su propio clic), en vez
                // de cerrarla por "clic afuera" y volver a abrirla.
                onInteractOutside={(evento) => {
                    if (
                        (evento.target as HTMLElement | null)?.closest(
                            '[data-test="notificaciones-button"]',
                        )
                    ) {
                        evento.preventDefault();
                    }
                }}
                data-test="notificaciones-panel"
            >
                <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
                    <div>
                        <p className="text-sm font-semibold">Notificaciones</p>
                        <p className="text-xs text-muted-foreground">
                            Correos de ingreso que enviaste
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => {
                            setActualizando(true);
                            void cargar().finally(() => setActualizando(false));
                        }}
                        aria-label="Actualizar"
                    >
                        <RefreshCw
                            className={cn(actualizando && 'animate-spin')}
                        />
                    </Button>
                </div>

                <ul className="max-h-[min(28rem,60dvh)] divide-y overflow-y-auto">
                    {notificaciones === null ? (
                        <li className="space-y-2 p-4">
                            {[0, 1, 2].map((fila) => (
                                <div
                                    key={fila}
                                    className="h-10 animate-pulse rounded-md bg-muted"
                                />
                            ))}
                        </li>
                    ) : notificaciones.length === 0 ? (
                        <li className="flex flex-col items-center gap-2 px-4 py-10 text-center text-sm text-muted-foreground">
                            <Bell className="size-8 opacity-40" />
                            Aquí verás si los correos de los ingresos se
                            enviaron o por qué fallaron.
                        </li>
                    ) : (
                        notificaciones.map((item) => (
                            <ItemNotificacion
                                key={item.id}
                                item={item}
                                onIr={() => setAbierta(false)}
                            />
                        ))
                    )}
                </ul>
            </PopoverContent>
        </Popover>
    );
}

function ItemNotificacion({
    item,
    onIr,
}: {
    item: NotificacionCorreo;
    onIr: () => void;
}) {
    const estado = ESTADOS[item.estado];
    const Icono = estado.icono;

    return (
        <li
            className={cn(
                'flex gap-3 px-4 py-3 text-sm',
                !item.leido && 'bg-primary/5',
            )}
            data-test="notificacion-correo"
        >
            <Icono className={cn('mt-0.5 size-5 shrink-0', estado.clase)} />
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="leading-snug">
                    Correo del ingreso{' '}
                    <span className="font-mono font-medium">
                        {item.ingreso_codigo ?? '—'}
                    </span>{' '}
                    a <span className="font-medium">{item.destinatario}</span>
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {item.correo}
                </p>
                <p className={cn('text-xs font-medium', estado.clase)}>
                    {estado.texto} · {formatearFechaHora(item.fecha)}
                </p>
                {item.estado === 'FALLIDO' && item.error ? (
                    <p
                        className="rounded-md bg-red-50 px-2 py-1 text-xs break-words text-red-700 dark:bg-red-950 dark:text-red-300"
                        data-test="notificacion-error"
                    >
                        {item.error}
                    </p>
                ) : null}
                <Link
                    href={ingresos()}
                    onClick={onIr}
                    className="inline-block text-xs text-primary hover:underline"
                >
                    Ir a ingresos
                </Link>
            </div>
            {!item.leido ? (
                <span
                    className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                    aria-label="Nueva"
                />
            ) : null}
        </li>
    );
}
