import { Link, useHttp } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarX,
    CheckCircle2,
    History,
    PackageX,
    Route,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { trazaNoRecibido } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import { CeldaCriticidad } from '@/components/dashboard/no-recibidos-cumplimiento';
import { LineaDeTiempo, Paso } from '@/components/linea-de-tiempo';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    faseLabel,
    formatearFechaSeguimiento,
    seguimientoLabel,
} from '@/lib/seguimiento-programacion';
import { show } from '@/routes/equipos';
import type {
    FaseSeguimiento,
    IngresoTraza,
    ServicioTraza,
    TrazaNoRecibido,
} from '@/types';
import { formatearFecha } from '@/lib/fechas';

type Props = {
    /** La programación no recibida; nula con la modal cerrada. */
    programacionId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Cumplido en verde, pendiente en ámbar, vencido en rojo. */
const ESTADO_RE_AGENDAMIENTO: Record<
    NonNullable<TrazaNoRecibido['re_agendamiento']>['estado'],
    { etiqueta: string; clase: string; detalle: string }
> = {
    CUMPLIDO: {
        etiqueta: 'Cumplido',
        clase: '!border-transparent !bg-emerald-500 !text-white',
        detalle: 'El equipo se recibió en un ingreso posterior.',
    },
    PENDIENTE: {
        etiqueta: 'Pendiente',
        clase: '!border-transparent !bg-amber-500 !text-white',
        detalle: 'La fecha aún no llega y el equipo no ha vuelto a ingresar.',
    },
    VENCIDO: {
        etiqueta: 'Vencido',
        clase: '!border-transparent !bg-red-500 !text-white',
        detalle: 'Pasó la fecha y el equipo no ha vuelto a ingresar.',
    },
};

const SERVICIOS: Record<ServicioTraza['servicio'], string> = {
    MANTENIMIENTO: 'Mantenimiento',
    CALIBRACION: 'Calibración',
    DESPACHO: 'Despacho',
};

function periodoDe(ingreso: IngresoTraza): string {
    return `${ingreso.bahia ?? 'Sin bahía'} · ${formatearFecha(ingreso.desde)} al ${formatearFecha(ingreso.hasta)}`;
}

/**
 * Traza de un equipo que no llegó al ingreso: cuándo estaba programado y en qué ingreso,
 * el seguimiento de esa programación, la novedad, el re-agendamiento (y si se cumplió) y
 * lo que pasó después con el equipo en los ingresos siguientes.
 */
export default function TrazaNoRecibidoModal({
    programacionId,
    open,
    onOpenChange,
}: Props) {
    const [traza, setTraza] = useState<TrazaNoRecibido | null>(null);
    const [error, setError] = useState(false);
    const consulta = useHttp<Record<string, never>, TrazaNoRecibido>();

    // Al abrir (o cambiar de equipo) se pide la traza.
    useEffect(() => {
        if (!open || programacionId === null) {
            return;
        }

        setTraza(null);
        setError(false);
        void consulta
            .get(trazaNoRecibido.url(programacionId))
            .then(setTraza)
            .catch(() => setError(true));
        // Solo al abrir o cambiar de programación.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, programacionId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[85vh] overflow-y-auto sm:max-w-xl"
                data-test="traza-no-recibido-modal"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Route className="h-5 w-5 text-muted-foreground" />
                        Traza del equipo
                    </DialogTitle>
                    <DialogDescription>
                        {traza ? (
                            <span className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={show(traza.programacion.equipo_id)}
                                    className="font-medium text-foreground underline-offset-2 hover:underline"
                                >
                                    {traza.programacion.equipo}
                                </Link>
                                · {traza.programacion.modelo}
                                <CeldaCriticidad
                                    criticidad={traza.programacion.criticidad}
                                />
                            </span>
                        ) : (
                            'Qué pasó con el equipo que no llegó al ingreso'
                        )}
                    </DialogDescription>
                </DialogHeader>

                {error ? (
                    <Alert variant="destructive">
                        <AlertDescription>
                            No se pudo cargar la traza del equipo.
                        </AlertDescription>
                    </Alert>
                ) : !traza ? (
                    <CargandoContenido mensaje="Cargando traza..." />
                ) : (
                    <TrazaDelEquipo traza={traza} />
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cerrar
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function TrazaDelEquipo({ traza }: { traza: TrazaNoRecibido }) {
    const { programacion, re_agendamiento: reAgendamiento } = traza;
    const ingreso = programacion.ingreso;

    return (
        <LineaDeTiempo testId="traza-linea-de-tiempo">
            <Paso icono={CalendarClock} titulo="Programado">
                <p>
                    {seguimientoLabel(
                        programacion.tipo_servicio.replaceAll(',', ' + '),
                    )}{' '}
                    para el {formatearFecha(programacion.fecha_programada, 'sin fecha')}
                </p>
                {ingreso ? (
                    <p>
                        Ingreso {periodoDe(ingreso)} ·{' '}
                        {seguimientoLabel(ingreso.estado_ingreso)}
                    </p>
                ) : null}
            </Paso>

            {traza.seguimientos.length > 0 ? (
                <Paso icono={History} titulo="Seguimiento de la programación">
                    <ul className="space-y-1.5">
                        {traza.seguimientos.map((seguimiento) => (
                            <li
                                key={seguimiento.id}
                                className="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                            >
                                <span className="text-foreground">
                                    {faseLabel(
                                        seguimiento.fase as FaseSeguimiento,
                                    )}
                                </span>
                                <Badge variant="secondary">
                                    {seguimientoLabel(seguimiento.seguimiento)}
                                </Badge>
                                <span className="text-xs">
                                    {seguimiento.fecha
                                        ? formatearFechaSeguimiento(
                                              seguimiento.fecha,
                                          )
                                        : ''}
                                    {seguimiento.usuario
                                        ? ` · ${seguimiento.usuario}`
                                        : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Paso>
            ) : null}

            <Paso icono={PackageX} titulo="No recibido" tono="malo">
                <p>
                    <span className="text-foreground">Novedad:</span>{' '}
                    {programacion.novedad ?? 'Sin novedad'}
                </p>
                {programacion.observacion ? (
                    <p>
                        <span className="text-foreground">Observación:</span>{' '}
                        {programacion.observacion}
                    </p>
                ) : null}
                {ingreso?.motivo_cancelacion ? (
                    <p>
                        <span className="text-foreground">
                            Motivo de cancelación del ingreso:
                        </span>{' '}
                        {ingreso.motivo_cancelacion}
                    </p>
                ) : null}
            </Paso>

            {reAgendamiento ? (
                <Paso
                    icono={CalendarClock}
                    tono={
                        reAgendamiento.estado === 'CUMPLIDO'
                            ? 'bueno'
                            : reAgendamiento.estado === 'VENCIDO'
                              ? 'malo'
                              : 'aviso'
                    }
                    titulo={
                        <span className="flex flex-wrap items-center gap-2">
                            {reAgendamiento.veces === 1
                                ? 'Re-agendado'
                                : `Re-agendado ${reAgendamiento.veces} veces`}
                            <Badge
                                className={
                                    ESTADO_RE_AGENDAMIENTO[reAgendamiento.estado]
                                        .clase
                                }
                                data-test="traza-estado-re-agendamiento"
                            >
                                {
                                    ESTADO_RE_AGENDAMIENTO[reAgendamiento.estado]
                                        .etiqueta
                                }
                            </Badge>
                        </span>
                    }
                >
                    <p>
                        {ESTADO_RE_AGENDAMIENTO[reAgendamiento.estado].detalle}
                    </p>
                    {reAgendamiento.cadena.length === 0 ? (
                        <p>
                            <span className="text-foreground">Para el:</span>{' '}
                            {formatearFecha(reAgendamiento.fecha, 'sin fecha')}
                        </p>
                    ) : null}
                </Paso>
            ) : null}

            {reAgendamiento?.cadena.map((eslabon, indice) => (
                <EslabonReAgendamiento
                    key={eslabon.id}
                    eslabon={eslabon}
                    numero={indice + 1}
                />
            ))}

            {traza.posteriores.map((siguiente) => (
                <Paso
                    key={siguiente.id}
                    icono={siguiente.ingresado ? CheckCircle2 : CalendarX}
                    tono={siguiente.ingresado ? 'bueno' : 'neutro'}
                    titulo={
                        siguiente.ingresado
                            ? `Recibido en un ingreso posterior${siguiente.ingreso.fecha_recepcion ? ` el ${formatearFecha(siguiente.ingreso.fecha_recepcion)}` : ''}`
                            : `En un ingreso posterior · ${seguimientoLabel(siguiente.estado_programacion)}`
                    }
                >
                    <p>Ingreso {periodoDe(siguiente.ingreso)}</p>
                    {siguiente.novedad ? (
                        <p>
                            <span className="text-foreground">Novedad:</span>{' '}
                            {siguiente.novedad}
                        </p>
                    ) : null}
                    <ListaServicios servicios={siguiente.servicios} />
                </Paso>
            ))}

            {traza.posteriores.length === 0 &&
            !reAgendamiento?.cadena.some((eslabon) => eslabon.ingreso) ? (
                <Paso icono={CalendarX} titulo="Después" tono="aviso">
                    <p data-test="traza-sin-posteriores">
                        Aún no ha vuelto a ingresar.
                    </p>
                </Paso>
            ) : null}
        </LineaDeTiempo>
    );
}

type Eslabon = NonNullable<
    TrazaNoRecibido['re_agendamiento']
>['cadena'][number];

/**
 * Una vez en que se re-agendó el equipo: para qué fecha quedó y qué pasó con esa
 * programación (sigue disponible, entró a un ingreso y llegó, o no llegó y se volvió a
 * re-agendar o se canceló).
 */
function EslabonReAgendamiento({
    eslabon,
    numero,
}: {
    eslabon: Eslabon;
    numero: number;
}) {
    const fecha = formatearFecha(eslabon.fecha, 'sin fecha');
    const { resultado, tono, icono } = eslabon.ingresado
        ? {
              resultado: 'llegó y se recibió',
              tono: 'bueno' as const,
              icono: CheckCircle2,
          }
        : eslabon.estado_programacion === 'PENDIENTE'
          ? {
                resultado: 'disponible para el próximo ingreso de su bahía',
                tono: 'aviso' as const,
                icono: CalendarClock,
            }
          : eslabon.estado_programacion === 'AGENDADO'
            ? {
                  resultado: 'agendado en un ingreso, aún no se recibe',
                  tono: 'aviso' as const,
                  icono: CalendarClock,
              }
            : eslabon.estado_programacion === 'RE_AGENDADO'
              ? {
                    resultado: 'no llegó y se volvió a re-agendar',
                    tono: 'malo' as const,
                    icono: PackageX,
                }
              : {
                    resultado: 'no llegó y se canceló',
                    tono: 'malo' as const,
                    icono: PackageX,
                };

    return (
        <Paso
            icono={icono}
            tono={tono}
            titulo={`${numero}.° re-agendamiento: para el ${fecha} — ${resultado}`}
        >
            <div data-test="traza-eslabon">
                {eslabon.ingreso ? (
                    <p>
                        Ingreso {periodoDe(eslabon.ingreso)}
                        {eslabon.ingresado && eslabon.ingreso.fecha_recepcion
                            ? ` · recibido el ${formatearFecha(eslabon.ingreso.fecha_recepcion)}`
                            : ''}
                    </p>
                ) : null}
                {!eslabon.ingresado && eslabon.novedad ? (
                    <p>
                        <span className="text-foreground">Novedad:</span>{' '}
                        {eslabon.novedad}
                    </p>
                ) : null}
                {!eslabon.ingresado && eslabon.observacion ? (
                    <p>
                        <span className="text-foreground">Observación:</span>{' '}
                        {eslabon.observacion}
                    </p>
                ) : null}
                <ListaServicios servicios={eslabon.servicios} />
            </div>
        </Paso>
    );
}

/** Mantenimiento, calibración y despacho de una programación, con su estado y fecha. */
function ListaServicios({ servicios }: { servicios: ServicioTraza[] }) {
    if (servicios.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-0.5">
            {servicios.map((servicio, indice) => (
                <li key={indice} className="flex flex-wrap items-center gap-2">
                    <span className="text-foreground">
                        {SERVICIOS[servicio.servicio]}
                        {servicio.tercero ? ' (tercero)' : ''}
                    </span>
                    <Badge variant="secondary">
                        {seguimientoLabel(servicio.estado)}
                    </Badge>
                    {servicio.fecha ? (
                        <span className="text-xs">
                            {formatearFecha(servicio.fecha)}
                        </span>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
