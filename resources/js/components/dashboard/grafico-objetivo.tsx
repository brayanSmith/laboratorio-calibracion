import { useId } from 'react';
import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import SelectorAgrupacion, {
    useAgrupacion,
} from '@/components/dashboard/selector-agrupacion';
import {
    agruparPromedios,
    COLOR_CUMPLE,
    COLOR_NO_CUMPLE,
    cortesDeColor,
    cumpleObjetivo,
    diferenciaConObjetivo,
    menorEsMejor,
    objetivoDelPeriodo,
} from '@/lib/periodos-grafico';
import { cn } from '@/lib/utils';
import type { ConceptoObjetivo, ObjetivosDelPeriodo } from '@/types';

type Props = {
    /** Opcional: dentro de un bloque con título no hace falta repetirlo. */
    titulo?: string;
    /** Nombre del valor en la leyenda y el globo (ej. "Tiempo promedio"). */
    etiquetaValor: string;
    /** Filas con su fecha y su valor (null si no tiene); se promedian por periodo. */
    filas: { fecha: string; valor: number | null }[];
    /** Periodo de los filtros: el eje lo cubre completo. */
    desde: string;
    hasta: string;
    objetivos: ObjetivosDelPeriodo;
    concepto: ConceptoObjetivo;
    /** Cómo se escribe un valor (ej. "3,5 días"). */
    formato: (valor: number) => string;
    /** Cómo se llaman las filas en el globo (ej. "equipos"). */
    nombreFilas: string;
    testId?: string;
};

type Punto = {
    inicio: string;
    etiqueta: string;
    valor: number | null;
    objetivo: number | null;
    cantidad: number;
};

/**
 * Área con el promedio de cada periodo (días, semanas, meses, trimestres, semestres o
 * años), verde donde cumple el objetivo y roja donde no, con un marcador pequeño en cada
 * uno, y el objetivo del año como una línea punteada. El globo de cada periodo muestra el valor, el objetivo y la diferencia, en verde si cumple y en rojo si no.
 */
export default function GraficoObjetivo({
    titulo,
    etiquetaValor,
    filas,
    desde,
    hasta,
    objetivos,
    concepto,
    formato,
    nombreFilas,
    testId,
}: Props) {
    const { agrupacion, elegir } = useAgrupacion(desde, hasta);
    const idGradiente = `grafico-${useId().replace(/:/g, '')}`;

    const datos: Punto[] = agruparPromedios(
        filas,
        desde,
        hasta,
        agrupacion,
    ).map((punto) => ({
        ...punto,
        objetivo: objetivoDelPeriodo(objetivos, punto.inicio, concepto),
    }));
    const hayDatos = datos.some(
        (dato) => dato.valor !== null || dato.objetivo !== null,
    );

    const menorMejor = menorEsMejor(concepto);
    // Si el periodo tiene objetivos distintos (dos años), cada etiqueta dice su año.
    const variosObjetivos =
        new Set(
            datos.map((dato) => dato.objetivo).filter((meta) => meta !== null),
        ).size > 1;
    const cortes = cortesDeColor(datos, menorMejor);
    const colorPunto = (punto: Punto) =>
        cumpleObjetivo(punto, menorMejor) ? COLOR_CUMPLE : COLOR_NO_CUMPLE;
    // Una línea plana (todos los valores iguales) no tiene alto y el SVG no le pinta un
    // degradado: va de un solo color (rojo si algún periodo no cumple).
    const valores = datos
        .map((dato) => dato.valor)
        .filter((valor): valor is number => valor !== null);
    const lineaPlana =
        valores.length > 0 && Math.min(...valores) === Math.max(...valores);
    const colorPlano = cortes.some((corte) => corte.color === COLOR_NO_CUMPLE)
        ? COLOR_NO_CUMPLE
        : COLOR_CUMPLE;

    const config = {
        valor: { label: etiquetaValor, color: COLOR_CUMPLE },
        objetivo: {
            label: 'Objetivo',
            color: 'var(--foreground)',
        },
    } satisfies ChartConfig;

    return (
        <div className="grid gap-3" data-test={testId}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                {titulo ? (
                    <p className="text-sm font-medium">{titulo}</p>
                ) : (
                    <span />
                )}
                <SelectorAgrupacion
                    agrupacion={agrupacion}
                    onChange={elegir}
                    desde={desde}
                    hasta={hasta}
                />
            </div>

            {!hayDatos ? (
                <p className="flex h-64 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                    Sin datos ni objetivo en este periodo.
                </p>
            ) : (
                <ChartContainer config={config} className="h-64 w-full">
                    <ComposedChart
                        data={datos}
                        margin={{ top: 16, right: 12, left: 4 }}
                    >
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis
                            dataKey="etiqueta"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            interval="preserveStartEnd"
                            minTickGap={16}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            width={72}
                            tickFormatter={(valor: number) => formato(valor)}
                        />
                        <ChartTooltip
                            cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <GloboPeriodo
                                        punto={payload[0].payload as Punto}
                                        etiquetaValor={etiquetaValor}
                                        formato={formato}
                                        nombreFilas={nombreFilas}
                                        menorEsMejor={menorMejor}
                                    />
                                ) : null
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <defs>
                            {/* Verde donde cumple y rojo donde no, para la línea y su relleno. */}
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
                            <linearGradient
                                id={`${idGradiente}-relleno`}
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
                                        stopOpacity={0.12}
                                    />
                                ))}
                            </linearGradient>
                        </defs>
                        {/* El promedio: verde donde cumple y rojo donde no, con un marcador pequeño
                            del color de cada periodo. Recto entre puntos, para que el color cambie
                            justo donde cruza el objetivo. */}
                        <Area
                            dataKey="valor"
                            type="linear"
                            stroke={
                                lineaPlana
                                    ? colorPlano
                                    : `url(#${idGradiente}-linea)`
                            }
                            strokeWidth={2}
                            fill={`url(#${idGradiente}-relleno)`}
                            dot={(props: {
                                cx?: number;
                                cy?: number;
                                index: number;
                                payload: Punto;
                            }) =>
                                props.cx === undefined ||
                                props.cy === undefined ||
                                props.payload.valor === null ? (
                                    <g key={props.index} />
                                ) : (
                                    <circle
                                        key={props.index}
                                        cx={props.cx}
                                        cy={props.cy}
                                        r={2.5}
                                        fill={colorPunto(props.payload)}
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
                                    r={4}
                                    fill={colorPunto(props.payload)}
                                    stroke="var(--background)"
                                    strokeWidth={1.5}
                                />
                            )}
                            connectNulls={false}
                        />
                        {/* El objetivo, sin marcadores; cambia de altura entre años. */}
                        <Line
                            dataKey="objetivo"
                            type="linear"
                            stroke="var(--color-objetivo)"
                            strokeWidth={2}
                            strokeDasharray="6 4"
                            dot={false}
                            activeDot={false}
                            connectNulls={false}
                            label={(props: {
                                x?: number | string;
                                y?: number | string;
                                index?: number;
                            }) => {
                                const indice = props.index ?? 0;
                                const actual = datos[indice]?.objetivo ?? null;
                                const anterior =
                                    datos[indice - 1]?.objetivo ?? null;

                                // Solo donde empieza un tramo del objetivo (al inicio y al cambiar de año).
                                if (
                                    actual === null ||
                                    (indice > 0 && anterior === actual)
                                ) {
                                    return <g key={indice} />;
                                }

                                return (
                                    <text
                                        key={indice}
                                        x={Number(props.x) + 4}
                                        y={Number(props.y) - 6}
                                        className="fill-foreground text-xs font-medium"
                                    >
                                        {variosObjetivos
                                            ? `Objetivo ${datos[indice].inicio.slice(0, 4)}: `
                                            : 'Objetivo: '}
                                        {formato(actual)}
                                    </text>
                                );
                            }}
                        />
                    </ComposedChart>
                </ChartContainer>
            )}
        </div>
    );
}

/**
 * Globo de un periodo: su valor, el objetivo y la diferencia entre ambos, en verde si
 * cumple el objetivo y en rojo si no.
 */
function GloboPeriodo({
    punto,
    etiquetaValor,
    formato,
    nombreFilas,
    menorEsMejor: menorMejor,
}: {
    punto: Punto;
    etiquetaValor: string;
    formato: (valor: number) => string;
    nombreFilas: string;
    menorEsMejor: boolean;
}) {
    const resultado = diferenciaConObjetivo(
        punto.valor,
        punto.objetivo,
        menorMejor,
    );

    return (
        <div className="grid min-w-48 gap-1.5 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
            <p className="font-medium">
                {punto.etiqueta}
                <span className="font-normal text-muted-foreground">
                    {' '}
                    · {punto.cantidad} {nombreFilas}
                </span>
            </p>
            <Fila
                color={
                    resultado === null || resultado.cumple
                        ? COLOR_CUMPLE
                        : COLOR_NO_CUMPLE
                }
                etiqueta={etiquetaValor}
                valor={punto.valor === null ? '—' : formato(punto.valor)}
            />
            <Fila
                color="var(--foreground)"
                etiqueta="Objetivo"
                valor={punto.objetivo === null ? '—' : formato(punto.objetivo)}
            />
            {resultado !== null ? (
                <DiferenciaObjetivo {...resultado} formato={formato} />
            ) : null}
        </div>
    );
}

export function Fila({
    color,
    etiqueta,
    valor,
}: {
    color: string;
    etiqueta: string;
    valor: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="flex items-center gap-1.5 text-muted-foreground">
                <span
                    className="h-2.5 w-2.5 rounded-sm"
                    style={{ backgroundColor: color }}
                />
                {etiqueta}
            </span>
            <span className="font-medium tabular-nums">{valor}</span>
        </div>
    );
}

/** "Cumple / No cumple" con la diferencia frente al objetivo, en verde o en rojo. */
export function DiferenciaObjetivo({
    diferencia,
    cumple,
    formato,
}: {
    diferencia: number;
    cumple: boolean;
    formato: (valor: number) => string;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 rounded-md px-1.5 py-1 font-medium',
                cumple
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                    : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300',
            )}
            data-test="globo-diferencia"
        >
            <span>{cumple ? 'Cumple' : 'No cumple'}</span>
            <span className="tabular-nums">
                {diferencia > 0 ? '+' : diferencia < 0 ? '−' : ''}
                {formato(Math.abs(diferencia))}
            </span>
        </div>
    );
}
