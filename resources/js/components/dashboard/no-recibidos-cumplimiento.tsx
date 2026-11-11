import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { Pie, PieChart } from 'recharts';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { Fila } from '@/components/dashboard/grafico-objetivo';
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
import { show } from '@/routes/equipos';
import type { CriticidadEquipo, Cumplimiento } from '@/types';

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
const MAX_PORCIONES = 6;

/**
 * Colores de las porciones, en orden fijo (la más usada siempre en azul), con su versión
 * para el modo oscuro; validados para daltonismo. "Otras" va en gris.
 */
const COLORES_PORCION: { light: string; dark: string }[] = [
    { light: '#2a78d6', dark: '#3987e5' },
    { light: '#eb6834', dark: '#d95926' },
    { light: '#1baf7a', dark: '#199e70' },
    { light: '#eda100', dark: '#c98500' },
    { light: '#e87ba4', dark: '#d55181' },
    { light: '#6250d6', dark: '#9085e9' },
];
const COLOR_OTRAS = { light: '#a3a29d', dark: '#6f6e69' };

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
            className="grid items-center gap-6 md:grid-cols-[16rem_1fr]"
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
                                    <div className="grid min-w-48 gap-1 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
                                        <span className="font-medium">
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
                        no recibidos
                    </span>
                </div>
            </div>

            <ul className="grid gap-2 text-sm">
                {porciones.map((porcion) => (
                    <li
                        key={porcion.clave}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                className="h-3 w-3 shrink-0 rounded-sm"
                                style={{ backgroundColor: porcion.fill }}
                            />
                            <span className="truncate" title={porcion.novedad}>
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

/**
 * Equipos que no llegaron al ingreso, los más críticos primero, con su novedad y si se
 * re-agendaron, para gestionarlos (el código abre la ficha del equipo).
 */
export function TablaNoRecibidos({
    noRecibidos,
}: {
    noRecibidos: Cumplimiento['no_recibidos'];
}) {
    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('equipo', {
                    header: 'Equipo',
                    cell: ({ row }) => (
                        <>
                            <Link
                                href={show(row.original.equipo_id)}
                                className="font-medium underline-offset-2 hover:underline"
                            >
                                {row.original.equipo}
                            </Link>
                            <span className="block text-xs text-muted-foreground">
                                {row.original.modelo}
                            </span>
                        </>
                    ),
                }),
                columnHelper.accessor((fila) => fila.tipo_equipo ?? '', {
                    id: 'tipo_equipo',
                    header: 'Tipo de equipo',
                    cell: ({ row }) => row.original.tipo_equipo ?? '—',
                }),
                columnHelper.accessor((fila) => fila.area ?? '', {
                    id: 'area',
                    header: 'Área',
                    cell: ({ row }) => row.original.area ?? '—',
                }),
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
                        filterOptions: estadosProgramacion.map((estado) => ({
                            value: estado.value,
                            label: estado.label,
                        })),
                    },
                    cell: ({ row }) => (
                        <>
                            {estadosProgramacion.find(
                                (estado) =>
                                    estado.value ===
                                    row.original.estado_programacion,
                            )?.label ?? row.original.estado_programacion}
                            {row.original.fecha_re_agendamiento ? (
                                <span className="block text-xs text-muted-foreground">
                                    Para el {row.original.fecha_re_agendamiento}
                                </span>
                            ) : null}
                        </>
                    ),
                }),
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                }),
            ]),
        [],
    );

    return (
        <DataTable
            data={noRecibidos}
            columns={columns}
            searchPlaceholder="Buscar equipo, novedad o área..."
            emptyMessage="Todos los equipos programados llegaron a su ingreso."
            rowTestId="cumplimiento-no-recibido-row"
        />
    );
}
