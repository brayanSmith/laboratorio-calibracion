import { useId } from 'react';
import {
    Area,
    Bar,
    CartesianGrid,
    ComposedChart,
    Line,
    XAxis,
    YAxis,
} from 'recharts';
import { Fila } from '@/components/dashboard/grafico-objetivo';
import SelectorAgrupacion, {
    useAgrupacion,
} from '@/components/dashboard/selector-agrupacion';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatearPorcentaje } from '@/lib/indicadores';
import {
    agruparCumplimiento,
    COLOR_CUMPLE,
    COLOR_NO_CUMPLE,
    cortesDeColor,
    objetivoDelPeriodo,
} from '@/lib/periodos-grafico';
import type { PuntoCumplimiento } from '@/lib/periodos-grafico';
import type { ObjetivosDelPeriodo } from '@/types';

type Props = {
    /** Cada equipo programado con su fecha y si se atendió. */
    equipos: { fecha: string; atendido: boolean }[];
    desde: string;
    hasta: string;
    /** Objetivo anual de cumplimiento: se dibuja como una línea punteada. */
    objetivos: ObjetivosDelPeriodo;
};

type Punto = PuntoCumplimiento & { objetivo: number | null };

/** Sin objetivo del año, la meta es el 90 % (desde ahí las tarjetas se ven en verde). */
const META_SIN_OBJETIVO = 90;

const metaDe = (punto: Punto) => punto.objetivo ?? META_SIN_OBJETIVO;

/** Verde si el periodo llega a su meta, rojo si no. */
const colorDe = (punto: Punto) =>
    (punto.porcentaje ?? 0) >= metaDe(punto) ? COLOR_CUMPLE : COLOR_NO_CUMPLE;

const config = {
    // Celeste y verde, como las tarjetas de arriba: con color, sin llegar a los tonos fuertes.
    programados: {
        label: 'Equipos programados',
        theme: { light: '#38bdf8', dark: '#0ea5e9' },
    },
    atendidos: {
        label: 'Equipos atendidos',
        theme: { light: '#34d399', dark: '#10b981' },
    },
    porcentaje: { label: 'Cumplimiento', color: COLOR_CUMPLE },
    objetivo: { label: 'Objetivo', color: '#64748b' },
} satisfies ChartConfig;

/**
 * Gráfico combinado del cumplimiento por periodo: columnas con los equipos programados y
 * los atendidos (eje izquierdo, cantidad) y un área con el porcentaje de cumplimiento
 * (eje derecho, 0 a 100 %), verde donde llega a su meta y roja donde no, con el objetivo
 * del año punteado.
 */
export default function GraficoCumplimiento({
    equipos,
    desde,
    hasta,
    objetivos,
}: Props) {
    const { agrupacion, elegir } = useAgrupacion(desde, hasta);
    const datos: Punto[] = agruparCumplimiento(
        equipos,
        desde,
        hasta,
        agrupacion,
    ).map((punto) => ({
        ...punto,
        objetivo: objetivoDelPeriodo(
            objetivos,
            punto.inicio,
            'CUMPLIMIENTO_PORCENTAJE',
        ),
    }));
    const hayObjetivo = datos.some((punto) => punto.objetivo !== null);
    const idGradiente = `cumplimiento-${useId().replace(/:/g, '')}`;
    // Verde donde llega a su meta y rojo donde no: el objetivo del año o, sin él, el 90 %
    // (el mismo corte de las tarjetas).
    const conMeta = datos.map((punto) => ({
        valor: punto.porcentaje,
        objetivo: metaDe(punto),
    }));
    const cortes = cortesDeColor(conMeta, false);
    const valores = datos
        .map((punto) => punto.porcentaje)
        .filter((valor): valor is number => valor !== null);
    // Una línea plana no tiene alto y el SVG no le pinta un degradado: va de un solo color.
    const lineaPlana =
        valores.length > 0 && Math.min(...valores) === Math.max(...valores);
    const colorPlano = cortes.some((corte) => corte.color === COLOR_NO_CUMPLE)
        ? COLOR_NO_CUMPLE
        : COLOR_CUMPLE;

    return (
        <div className="grid gap-3" data-test="grafico-cumplimiento">
            <div className="flex justify-end">
                <SelectorAgrupacion
                    agrupacion={agrupacion}
                    onChange={elegir}
                    desde={desde}
                    hasta={hasta}
                />
            </div>

            {equipos.length === 0 ? (
                <p className="flex h-72 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                    Sin equipos programados en este periodo.
                </p>
            ) : (
                <ChartContainer config={config} className="h-72 w-full">
                    <ComposedChart
                        data={datos}
                        margin={{ top: 12, right: 4, left: 4 }}
                        barGap={2}
                    >
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis
                            dataKey="etiqueta"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            interval="preserveStartEnd"
                            minTickGap={16}
                        />
                        <YAxis
                            yAxisId="cantidad"
                            tickLine={false}
                            axisLine={false}
                            width={40}
                            allowDecimals={false}
                            label={{
                                value: 'Equipos',
                                angle: -90,
                                position: 'insideLeft',
                                className: 'fill-muted-foreground text-xs',
                            }}
                        />
                        <YAxis
                            yAxisId="porcentaje"
                            orientation="right"
                            domain={[0, 100]}
                            ticks={[0, 25, 50, 75, 100]}
                            tickLine={false}
                            axisLine={false}
                            width={44}
                            tickFormatter={(valor: number) => `${valor} %`}
                        />
                        <ChartTooltip
                            cursor={{ fill: 'var(--muted)', opacity: 0.4 }}
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <Globo
                                        punto={payload[0].payload as Punto}
                                    />
                                ) : null
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <defs>
                            {/* Verde donde llega a la meta y rojo donde no, de izquierda a derecha. */}
                            <linearGradient
                                id={`${idGradiente}-linea`}
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="0"
                            >
                                {cortes.map((corte, indice) => (
                                    <stop
                                        key={indice}
                                        offset={corte.offset}
                                        stopColor={corte.color}
                                    />
                                ))}
                            </linearGradient>
                            {/* Velo del color de la tarjeta: transparente arriba y opaco abajo, para
                                que el relleno se desvanezca hacia el eje. */}
                            <linearGradient
                                id={`${idGradiente}-velo`}
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0"
                                    style={{
                                        stopColor: 'var(--card)',
                                        stopOpacity: 0,
                                    }}
                                />
                                <stop
                                    offset="1"
                                    style={{
                                        stopColor: 'var(--card)',
                                        stopOpacity: 1,
                                    }}
                                />
                            </linearGradient>
                        </defs>
                        {/* El área en capas, debajo de las columnas: el relleno del color de cada
                            tramo y encima el velo que lo desvanece hacia abajo. */}
                        <Area
                            yAxisId="porcentaje"
                            dataKey="porcentaje"
                            type="monotone"
                            stroke="none"
                            fill={
                                lineaPlana
                                    ? colorPlano
                                    : `url(#${idGradiente}-linea)`
                            }
                            fillOpacity={0.35}
                            dot={false}
                            activeDot={false}
                            legendType="none"
                            connectNulls
                            isAnimationActive={false}
                        />
                        <Area
                            yAxisId="porcentaje"
                            dataKey="porcentaje"
                            type="monotone"
                            stroke="none"
                            fill={`url(#${idGradiente}-velo)`}
                            dot={false}
                            activeDot={false}
                            legendType="none"
                            connectNulls
                            isAnimationActive={false}
                        />
                        <Bar
                            yAxisId="cantidad"
                            dataKey="programados"
                            fill="var(--color-programados)"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={28}
                        />
                        <Bar
                            yAxisId="cantidad"
                            dataKey="atendidos"
                            fill="var(--color-atendidos)"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={28}
                        />
                        {/* La línea, curva suave; el color cambia donde la recta entre los dos
                            puntos cruza la meta. */}
                        <Area
                            yAxisId="porcentaje"
                            dataKey="porcentaje"
                            type="monotone"
                            stroke={
                                lineaPlana
                                    ? colorPlano
                                    : `url(#${idGradiente}-linea)`
                            }
                            strokeWidth={2.5}
                            fill="none"
                            dot={(props: {
                                cx?: number;
                                cy?: number;
                                index: number;
                                payload: Punto;
                            }) =>
                                props.cx === undefined ||
                                props.cy === undefined ||
                                props.payload.porcentaje === null ? (
                                    <g key={props.index} />
                                ) : (
                                    <circle
                                        key={props.index}
                                        cx={props.cx}
                                        cy={props.cy}
                                        r={3}
                                        fill={colorDe(props.payload)}
                                    />
                                )
                            }
                            activeDot={(props: {
                                cx?: number;
                                cy?: number;
                                payload: Punto;
                            }) => (
                                <circle
                                    cx={props.cx}
                                    cy={props.cy}
                                    r={5}
                                    fill={colorDe(props.payload)}
                                    stroke="var(--background)"
                                    strokeWidth={2}
                                />
                            )}
                            connectNulls
                        />
                        {hayObjetivo ? (
                            <Line
                                yAxisId="porcentaje"
                                dataKey="objetivo"
                                type="stepAfter"
                                stroke="var(--color-objetivo)"
                                strokeOpacity={0.6}
                                strokeWidth={2}
                                strokeDasharray="6 4"
                                dot={false}
                                activeDot={false}
                            />
                        ) : null}
                    </ComposedChart>
                </ChartContainer>
            )}
        </div>
    );
}

/** Globo de un periodo: programados, atendidos, cumplimiento y objetivo. */
function Globo({ punto }: { punto: Punto }) {
    return (
        <div className="grid min-w-52 gap-1.5 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
            <p className="font-medium">{punto.etiqueta}</p>
            <Fila
                color="var(--color-programados)"
                etiqueta="Equipos programados"
                valor={String(punto.programados)}
            />
            <Fila
                color="var(--color-atendidos)"
                etiqueta="Equipos atendidos"
                valor={String(punto.atendidos)}
            />
            <Fila
                color={
                    punto.porcentaje === null ? 'transparent' : colorDe(punto)
                }
                etiqueta="Cumplimiento"
                valor={formatearPorcentaje(punto.porcentaje)}
            />
            <Fila
                color="transparent"
                etiqueta={
                    punto.objetivo !== null ? 'Objetivo' : 'Meta (sin objetivo)'
                }
                valor={formatearPorcentaje(metaDe(punto))}
            />
            {punto.porcentaje !== null ? (
                <p className="font-medium" style={{ color: colorDe(punto) }}>
                    {colorDe(punto) === COLOR_CUMPLE
                        ? '✓ Cumple'
                        : '✗ No cumple'}
                </p>
            ) : null}
        </div>
    );
}
