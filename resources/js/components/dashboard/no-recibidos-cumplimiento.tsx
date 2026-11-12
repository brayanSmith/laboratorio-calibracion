import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Pie, PieChart } from 'recharts';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { Fila } from '@/components/dashboard/grafico-objetivo';
import TrazaNoRecibidoModal from '@/components/dashboard/traza-no-recibido-modal';
import { estadosProgramacion } from '@/components/equipos/equipo-programacion-fields';
import { Badge } from '@/components/ui/badge';
import { ChartContainer, ChartTooltip } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    colorCriticidadEquipo,
    criticidadesEquipo,
    etiquetaCriticidadEquipo,
    rangoCriticidadEquipo,
} from '@/lib/criticidad-equipo';
import { formatearPorcentaje } from '@/lib/indicadores';
import { cn } from '@/lib/utils';
import { show } from '@/routes/equipos';
import type { CriticidadEquipo, Cumplimiento } from '@/types';
import { formatearFecha } from '@/lib/fechas';
import {
    CeldaEquipo,
    CeldaUbicacion,
    textoEquipo,
} from '@/components/equipos/celdas-equipo';

type NoRecibido = Cumplimiento['no_recibidos'][number];

/** Badge de la criticidad del equipo: alta en rojo, media en amarillo, baja en verde. */
export function CeldaCriticidad({
    criticidad,
}: {
    criticidad: CriticidadEquipo | null;
}) {
    return criticidad ? (
        <Badge className={colorCriticidadEquipo[criticidad]}>
            {etiquetaCriticidadEquipo(criticidad)}
        </Badge>
    ) : (
        <span className="text-muted-foreground">Sin asignar</span>
    );
}

/** Opciones del filtro de la columna de criticidad. */
export const filtroCriticidad = criticidadesEquipo.map((opcion) => ({
    value: opcion.value,
    label: opcion.label,
}));

type ConCriticidad = { original: { criticidad: CriticidadEquipo | null } };

/** Ordena la columna por criticidad (alta, media, baja, sin asignar), no alfabéticamente. */
export function ordenarPorCriticidad(
    a: ConCriticidad,
    b: ConCriticidad,
): number {
    return (
        rangoCriticidadEquipo(a.original.criticidad) -
        rangoCriticidadEquipo(b.original.criticidad)
    );
}

/** Hasta cuántas porciones se dibujan: las demás novedades se juntan en "Otras". */
const MAX_PORCIONES = 5;

/**
 * Colores de las porciones (la paleta que pidió el cliente), en orden fijo: la más usada
 * siempre en rojo. Iguales en modo claro y oscuro. El azul y el verde azulado no van
 * seguidos, porque juntos casi no se distinguen. Con más de 5 novedades, las últimas se
 * juntan en "Otras", en el gris de la misma paleta.
 */
const COLORES_PORCION: { light: string; dark: string }[] = [
    { light: '#E57373', dark: '#E57373' },
    { light: '#64B5F6', dark: '#64B5F6' },
    { light: '#FFB74D', dark: '#FFB74D' },
    { light: '#4DB6AC', dark: '#4DB6AC' },
    // Solo si hay justo 5 novedades (con más, la quinta porción es "Otras").
    { light: '#9575CD', dark: '#9575CD' },
];
/** "Otras": el gris de la paleta del cliente. */
const COLOR_OTRAS = { light: '#B0BEC5', dark: '#B0BEC5' };

type Porcion = {
    clave: string;
    novedad: string;
    cantidad: number;
    porcentaje: number | null;
    fill: string;
};

/**
 * Las porciones de la torta: las novedades de la más a la menos usada y, si hay más de
 * las que caben, las últimas juntas en "Otras".
 */
function porcionesDe(
    novedades: Cumplimiento['novedades_no_recibidos'],
): Porcion[] {
    const total = novedades.reduce((suma, fila) => suma + fila.cantidad, 0);
    const visibles =
        novedades.length > MAX_PORCIONES
            ? novedades.slice(0, MAX_PORCIONES - 1)
            : novedades;
    const resto = novedades.slice(visibles.length);
    const porciones = visibles.map((fila, indice) => ({
        clave: `n${indice}`,
        ...fila,
        fill: `var(--color-n${indice})`,
    }));

    if (resto.length === 0) {
        return porciones;
    }

    const cantidad = resto.reduce((suma, fila) => suma + fila.cantidad, 0);

    return [
        ...porciones,
        {
            clave: 'otras',
            novedad: `Otras (${resto.length})`,
            cantidad,
            porcentaje: Math.round((cantidad * 1000) / total) / 10,
            fill: 'var(--color-otras)',
        },
    ];
}

/**
 * Por qué no llegaron los equipos al ingreso: una torta (dona) con una porción por
 * novedad y, al lado, la leyenda con cuántos equipos y qué parte del total son.
 */
export function NovedadesNoRecibidos({
    novedades,
}: {
    novedades: Cumplimiento['novedades_no_recibidos'];
}) {
    const porciones = useMemo(() => porcionesDe(novedades), [novedades]);
    const config = useMemo(
        () =>
            Object.fromEntries(
                porciones.map((porcion, indice) => [
                    porcion.clave,
                    {
                        label: porcion.novedad,
                        theme:
                            porcion.clave === 'otras'
                                ? COLOR_OTRAS
                                : COLORES_PORCION[indice],
                    },
                ]),
            ) satisfies ChartConfig,
        [porciones],
    );
    const total = novedades.reduce((suma, fila) => suma + fila.cantidad, 0);

    if (novedades.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Todos los equipos programados llegaron a su ingreso.
            </p>
        );
    }

    return (
        <div
            // grid-cols-1 (y no la columna automática) para que la leyenda no crezca con
            // los nombres largos y se salga de la tarjeta.
            className="grid min-w-0 grid-cols-1 items-center gap-6 sm:grid-cols-[16rem_minmax(0,1fr)] lg:grid-cols-1 2xl:grid-cols-[16rem_minmax(0,1fr)]"
            data-test="cumplimiento-novedades-no-recibidos"
        >
            <div className="relative mx-auto h-64 w-64">
                <ChartContainer config={config} className="h-64 w-64">
                    <PieChart>
                        <ChartTooltip
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) {
                                    return null;
                                }

                                const porcion = payload[0].payload as Porcion;

                                return (
                                    <div className="grid w-56 max-w-[70vw] gap-1 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
                                        <span className="font-medium break-words">
                                            {porcion.novedad}
                                        </span>
                                        <Fila
                                            color={porcion.fill}
                                            etiqueta="Equipos"
                                            valor={`${porcion.cantidad} (${formatearPorcentaje(porcion.porcentaje)})`}
                                        />
                                    </div>
                                );
                            }}
                        />
                        <Pie
                            data={porciones}
                            dataKey="cantidad"
                            nameKey="novedad"
                            innerRadius={68}
                            outerRadius={110}
                            stroke="var(--background)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        />
                    </PieChart>
                </ChartContainer>
                {/* El total en el centro de la dona. */}
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-3xl font-semibold tabular-nums">
                        {total}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {total === 1 ? 'vez que no llegó' : 'veces que no llegó'}
                    </span>
                </div>
            </div>

            <ul className="grid min-w-0 gap-2 text-sm">
                {porciones.map((porcion) => (
                    <li
                        key={porcion.clave}
                        className="flex min-w-0 items-start justify-between gap-3"
                    >
                        {/* El nombre se parte en varias líneas: se lee completo y no se sale. */}
                        <span className="flex min-w-0 items-start gap-2">
                            <span
                                className="mt-1 h-3 w-3 shrink-0 rounded-sm"
                                style={{ backgroundColor: porcion.fill }}
                            />
                            <span className="min-w-0 break-words">
                                {porcion.novedad}
                            </span>
                        </span>
                        <span className="shrink-0 text-muted-foreground tabular-nums">
                            <strong className="font-medium text-foreground">
                                {porcion.cantidad}
                            </strong>{' '}
                            · {formatearPorcentaje(porcion.porcentaje)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}


const columnHelper = createDataTableColumnHelper<NoRecibido>();

/** Cancelado en rojo, re-agendado en ámbar. */
const COLOR_ESTADO: Record<string, string> = {
    CANCELADO: '!border-transparent !bg-red-500 !text-white',
    RE_AGENDADO: '!border-transparent !bg-amber-500 !text-white',
};

/**
 * Equipos que no llegaron al ingreso, los más críticos primero, con su novedad y si se
 * re-agendaron, para gestionarlos (el código abre la ficha del equipo).
 */
export function TablaNoRecibidos({
    noRecibidos,
}: {
    noRecibidos: Cumplimiento['no_recibidos'];
}) {
    const [trazaDe, setTrazaDe] = useState<number | null>(null);
    const [trazaAbierta, setTrazaAbierta] = useState(false);
    const columns = useMemo(
        () =>
            columnHelper.columns([
                // Como en la tabla de equipos: código (enlace a su ficha) y tipo; modelo y serie.
                columnHelper.accessor(
                    (fila) =>
                        textoEquipo(
                            fila.equipo,
                            fila.tipo_equipo,
                            fila.modelo,
                            fila.numero_serie,
                        ),
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <CeldaEquipo
                                codigo={
                                    <Link
                                        href={show(row.original.equipo_id)}
                                        className="underline-offset-2 hover:underline"
                                        // Abre la ficha del equipo, no la traza de la fila.
                                        onClick={(evento) =>
                                            evento.stopPropagation()
                                        }
                                    >
                                        {row.original.equipo}
                                    </Link>
                                }
                                tipo={row.original.tipo_equipo}
                                modelo={row.original.modelo}
                                serie={row.original.numero_serie}
                            />
                        ),
                    },
                ),
                columnHelper.accessor(
                    (fila) => textoEquipo(fila.area, fila.bahia),
                    {
                        id: 'ubicacion',
                        header: 'Área / Bahía',
                        cell: ({ row }) => (
                            <CeldaUbicacion
                                area={row.original.area}
                                bahia={row.original.bahia}
                            />
                        ),
                    },
                ),
                columnHelper.accessor((fila) => fila.criticidad ?? '', {
                    id: 'criticidad',
                    header: 'Criticidad',
                    sortFn: ordenarPorCriticidad,
                    meta: { filterOptions: filtroCriticidad },
                    cell: ({ row }) => (
                        <CeldaCriticidad criticidad={row.original.criticidad} />
                    ),
                }),
                columnHelper.accessor((fila) => fila.novedad ?? '', {
                    id: 'novedad',
                    header: 'Novedad',
                    cell: ({ row }) => (
                        <>
                            <span>{row.original.novedad ?? 'Sin novedad'}</span>
                            {row.original.observacion ? (
                                <span className="block max-w-xs truncate text-xs text-muted-foreground">
                                    {row.original.observacion}
                                </span>
                            ) : null}
                        </>
                    ),
                }),
                columnHelper.accessor('estado_programacion', {
                    header: 'Estado',
                    meta: {
                        filterOptions: estadosProgramacion
                            .filter((estado) => estado.value in COLOR_ESTADO)
                            .map((estado) => ({
                                value: estado.value,
                                label: estado.label,
                            })),
                    },
                    cell: ({ row }) => (
                        <>
                            <Badge
                                className={
                                    COLOR_ESTADO[row.original.estado_programacion]
                                }
                                variant={
                                    COLOR_ESTADO[row.original.estado_programacion]
                                        ? 'default'
                                        : 'secondary'
                                }
                                data-test="no-recibido-estado"
                            >
                                {estadosProgramacion.find(
                                    (estado) =>
                                        estado.value ===
                                        row.original.estado_programacion,
                                )?.label ?? row.original.estado_programacion}
                            </Badge>
                            {row.original.veces_no_recibido > 1 ? (
                                <span
                                    className="block text-xs text-muted-foreground"
                                    data-test="no-recibido-veces"
                                >
                                    No llegó {row.original.veces_no_recibido} veces
                                </span>
                            ) : null}
                        </>
                    ),
                }),
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                    // La fecha original y, si se re-agendó, la nueva debajo.
                    cell: ({ row }) => (
                        <>
                            <span
                                className={cn(
                                    row.original.fecha_re_agendamiento &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {formatearFecha(row.original.fecha_programada)}
                            </span>
                            {row.original.fecha_re_agendamiento ? (
                                <span
                                    className="block text-xs font-medium text-amber-700 dark:text-amber-400"
                                    data-test="no-recibido-fecha-re-agendada"
                                >
                                    Re-agendado al{' '}
                                    {formatearFecha(row.original.fecha_re_agendamiento)}
                                </span>
                            ) : null}
                        </>
                    ),
                }),
            ]),
        [],
    );

    return (
        <div className="grid gap-2">
            <p className="text-xs text-muted-foreground">
                Haz clic en un equipo para ver su traza: el seguimiento, el
                re-agendamiento y lo que pasó después.
            </p>
            <DataTable
                data={noRecibidos}
                columns={columns}
                searchPlaceholder="Buscar equipo, serie, novedad, área o bahía..."
                emptyMessage="Todos los equipos programados llegaron a su ingreso."
                rowTestId="cumplimiento-no-recibido-row"
                // Los de criticidad alta, resaltados en rojo: se deben atender primero.
                claseFila={(fila) =>
                    fila.criticidad === 'ALTA'
                        ? 'bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-950/60 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-red-500'
                        : undefined
                }
                onFilaClick={(fila) => {
                    setTrazaDe(fila.programacion_id);
                    setTrazaAbierta(true);
                }}
            />
            <TrazaNoRecibidoModal
                programacionId={trazaDe}
                open={trazaAbierta}
                onOpenChange={setTrazaAbierta}
            />
        </div>
    );
}
