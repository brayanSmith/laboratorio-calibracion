import { CartesianGrid, ComposedChart, Line, XAxis, YAxis } from 'recharts';
import {
    DiferenciaObjetivo,
    Fila,
} from '@/components/dashboard/grafico-objetivo';
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
import {
    agruparPromedios,
    diferenciaConObjetivo,
} from '@/lib/periodos-grafico';
import type { Agrupacion } from '@/lib/periodos-grafico';

/** Una serie del gráfico (ej. mantenimientos) con las filas que la forman. */
export type SerieGrafico = {
    clave: string;
    etiqueta: string;
    color: string;
    /** Si es mejor quedar por debajo del objetivo (tiempos) o por encima (cantidades). */
    menorEsMejor: boolean;
    /** Objetivo de un periodo (por su inicio y la agrupación); null si no tiene. */
    objetivo: (inicio: string, agrupacion: Agrupacion) => number | null;
    filas: { fecha: string; valor: number | null }[];
};

type Props = {
    series: SerieGrafico[];
    /** Qué se dibuja de cada periodo: el promedio de los valores o cuántas filas hay. */
    medida: 'promedio' | 'cantidad';
    desde: string;
    hasta: string;
    formato: (valor: number) => string;
    /** Cómo se llaman las filas en el globo (ej. "servicios"). */
    nombreFilas: string;
    testId?: string;
};

type Punto = { etiqueta: string } & Record<string, number | string | null>;

const claveObjetivo = (clave: string) => `objetivo_${clave}`;
const claveCantidad = (clave: string) => `cantidad_${clave}`;

/**
 * Líneas por periodo (días, semanas, meses, trimestres, semestres o años), una por serie,
 * cada una con su objetivo del año como una línea punteada de su mismo color. El globo de
 * cada periodo muestra, por serie, el valor, el objetivo y si cumple.
 */
export default function GraficoSeries({
    series,
    medida,
    desde,
    hasta,
    formato,
    nombreFilas,
    testId,
}: Props) {
    const { agrupacion, elegir } = useAgrupacion(desde, hasta);

    const porSerie = series.map((serie) =>
        agruparPromedios(serie.filas, desde, hasta, agrupacion),
    );
    const datos: Punto[] = (porSerie[0] ?? []).map((punto, indice) => {
        const fila: Punto = { etiqueta: punto.etiqueta };

        series.forEach((serie, posicion) => {
            const delPeriodo = porSerie[posicion][indice];

            fila[serie.clave] =
                medida === 'cantidad' ? delPeriodo.cantidad : delPeriodo.valor;
            fila[claveCantidad(serie.clave)] = delPeriodo.cantidad;
            fila[claveObjetivo(serie.clave)] = serie.objetivo(
                punto.inicio,
                agrupacion,
            );
        });

        return fila;
    });

    const hayDatos =
        series.some((serie) => serie.filas.length > 0) ||
        datos.some((dato) =>
            series.some((serie) => dato[claveObjetivo(serie.clave)] !== null),
        );

    const config = Object.fromEntries(
        series.flatMap((serie) => [
            [serie.clave, { label: serie.etiqueta, color: serie.color }],
            [
                claveObjetivo(serie.clave),
                {
                    label: `Objetivo ${serie.etiqueta.toLowerCase()}`,
                    color: serie.color,
                },
            ],
        ]),
    ) satisfies ChartConfig;

    return (
        <div className="grid gap-3" data-test={testId}>
            <div className="flex justify-end">
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
                        margin={{ top: 20, right: 12, left: 4 }}
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
                            allowDecimals={medida !== 'cantidad'}
                            tickFormatter={(valor: number) => formato(valor)}
                        />
                        <ChartTooltip
                            cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <GloboSeries
                                        punto={payload[0].payload as Punto}
                                        series={series}
                                        formato={formato}
                                        nombreFilas={nombreFilas}
                                    />
                                ) : null
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        {series.map((serie) => (
                            <Line
                                key={serie.clave}
                                dataKey={serie.clave}
                                type="linear"
                                stroke={`var(--color-${serie.clave})`}
                                strokeWidth={2}
                                dot={{
                                    r: 2.5,
                                    fill: `var(--color-${serie.clave})`,
                                    strokeWidth: 0,
                                }}
                                activeDot={{ r: 4 }}
                                connectNulls={false}
                            />
                        ))}
                        {series.map((serie, posicion) => (
                            <Line
                                key={claveObjetivo(serie.clave)}
                                dataKey={claveObjetivo(serie.clave)}
                                type="linear"
                                stroke={`var(--color-${serie.clave})`}
                                strokeOpacity={0.7}
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
                                    const clave = claveObjetivo(serie.clave);
                                    const actual =
                                        datos[indice]?.[clave] ?? null;
                                    const primero = datos.findIndex(
                                        (dato) => dato[clave] !== null,
                                    );

                                    // Solo al inicio de la línea: el objetivo puede cambiar cada mes.
                                    if (actual === null || indice !== primero) {
                                        return <g key={indice} />;
                                    }

                                    return (
                                        <text
                                            key={indice}
                                            x={Number(props.x) + 4}
                                            // Una etiqueta arriba y la otra abajo de su línea, para que no se encimen.
                                            y={
                                                Number(props.y) +
                                                (posicion === 0 ? -6 : 14)
                                            }
                                            className="text-xs font-medium"
                                            fill={serie.color}
                                        >
                                            Objetivo: {formato(Number(actual))}
                                        </text>
                                    );
                                }}
                            />
                        ))}
                    </ComposedChart>
                </ChartContainer>
            )}
        </div>
    );
}

/** Globo de un periodo: por cada serie, su valor, su objetivo y si cumple. */
function GloboSeries({
    punto,
    series,
    formato,
    nombreFilas,
}: {
    punto: Punto;
    series: SerieGrafico[];
    formato: (valor: number) => string;
    nombreFilas: string;
}) {
    return (
        <div className="grid min-w-56 gap-2 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
            <p className="font-medium">{punto.etiqueta}</p>
            {series.map((serie) => {
                const valor = punto[serie.clave] as number | null;
                const objetivo = punto[claveObjetivo(serie.clave)] as
                    number | null;
                const resultado = diferenciaConObjetivo(
                    valor,
                    objetivo,
                    serie.menorEsMejor,
                );

                return (
                    <div key={serie.clave} className="grid gap-1">
                        <Fila
                            color={serie.color}
                            etiqueta={`${serie.etiqueta} · ${punto[claveCantidad(serie.clave)]} ${nombreFilas}`}
                            valor={valor === null ? '—' : formato(valor)}
                        />
                        <Fila
                            color="transparent"
                            etiqueta="Objetivo"
                            valor={objetivo === null ? '—' : formato(objetivo)}
                        />
                        {resultado !== null ? (
                            <DiferenciaObjetivo
                                {...resultado}
                                formato={formato}
                            />
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}
