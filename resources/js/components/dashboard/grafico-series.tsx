import { useId } from 'react';
import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    useXAxisScale,
    XAxis,
    YAxis,
} from 'recharts';
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
    COLOR_CUMPLE,
    COLOR_NO_CUMPLE,
    cortesDeColor,
    diferenciaConObjetivo,
    techoDelEje,
    variacionConAnterior,
} from '@/lib/periodos-grafico';
import { formatearPorcentaje } from '@/lib/indicadores';
import { cn } from '@/lib/utils';
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
    /**
     * Sombrea las zonas por encima y por debajo del objetivo de cada serie (en tiempos,
     * encima rojo y debajo verde; en cantidades al revés), más intensas lejos del objetivo,
     * y colorea su línea: verde en los tramos que cumplen y roja donde no.
     */
    sombrearObjetivo?: boolean;
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
    sombrearObjetivo = false,
    testId,
}: Props) {
    const { agrupacion, elegir } = useAgrupacion(desde, hasta);
    const idGradiente = `series-${useId().replace(/:/g, '')}`;

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

    /** El periodo anterior al del globo (por su etiqueta); null en el primero. */
    const anteriorDe = (punto: Punto): Punto | null => {
        const indice = datos.findIndex(
            (dato) => dato.etiqueta === punto.etiqueta,
        );

        return indice > 0 ? datos[indice - 1] : null;
    };

    // Con zonas: el eje tiene un techo fijo (con un poco de aire sobre el máximo) hasta el
    // que llega la zona de encima del objetivo.
    const eje = techoDelEje(
        Math.max(
            0,
            ...datos.flatMap((dato) =>
                series.flatMap((serie) =>
                    [dato[serie.clave], dato[claveObjetivo(serie.clave)]].map(
                        (valor) => (typeof valor === 'number' ? valor : 0),
                    ),
                ),
            ),
        ) * 1.1,
        medida === 'cantidad',
    );

    // Cortes verde/rojo de la línea de cada objetivo, de su primer a su último periodo con
    // objetivo. Un periodo sin valor no incumple: cuenta como si llegara justo al objetivo.
    const cortesPorSerie = series.map((serie) =>
        cortesDeColor(
            datos.map((dato) => {
                const objetivo = dato[claveObjetivo(serie.clave)] as
                    | number
                    | null;
                const valor = dato[serie.clave] as number | null;

                return {
                    valor: objetivo === null ? null : (valor ?? objetivo),
                    objetivo,
                };
            }),
            serie.menorEsMejor,
        ),
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
                            {...(sombrearObjetivo
                                ? {
                                      domain: [0, eje.techo],
                                      ticks: eje.marcas,
                                      allowDataOverflow: true,
                                  }
                                : {})}
                        />
                        <ChartTooltip
                            cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <GloboSeries
                                        punto={payload[0].payload as Punto}
                                        anterior={anteriorDe(
                                            payload[0].payload as Punto,
                                        )}
                                        series={series}
                                        formato={formato}
                                        nombreFilas={nombreFilas}
                                    />
                                ) : null
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        {sombrearObjetivo ? (
                            <DegradadosObjetivo
                                idGradiente={idGradiente}
                                series={series}
                                cortesPorSerie={cortesPorSerie}
                                datos={datos}
                            />
                        ) : null}
                        {/* Dos zonas por serie, partidas por su objetivo: encima (del objetivo al
                            techo) y debajo (del eje al objetivo), cada una más intensa cuanto
                            más lejos del objetivo, como un mapa de calor. */}
                        {sombrearObjetivo
                            ? series.flatMap((serie) => {
                                  const clave = claveObjetivo(serie.clave);

                                  return [
                                      <Area
                                          key={`encima_${serie.clave}`}
                                          dataKey={(dato: Punto) =>
                                              dato[clave] === null
                                                  ? null
                                                  : [dato[clave], eje.techo]
                                          }
                                          type="linear"
                                          stroke="none"
                                          fill={`url(#${idGradiente}-encima-${serie.clave})`}
                                          dot={false}
                                          activeDot={false}
                                          legendType="none"
                                          tooltipType="none"
                                          connectNulls={false}
                                          isAnimationActive={false}
                                      />,
                                      <Area
                                          key={`debajo_${serie.clave}`}
                                          dataKey={clave}
                                          type="linear"
                                          stroke="none"
                                          fill={`url(#${idGradiente}-debajo-${serie.clave})`}
                                          dot={false}
                                          activeDot={false}
                                          legendType="none"
                                          tooltipType="none"
                                          connectNulls={false}
                                          isAnimationActive={false}
                                      />,
                                  ];
                              })
                            : null}
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
                                // Sombreado: la línea también va verde donde cumple y roja donde se pasa.
                                stroke={
                                    sombrearObjetivo &&
                                    cortesPorSerie[posicion].length > 0
                                        ? `url(#${idGradiente}-${serie.clave})`
                                        : `var(--color-${serie.clave})`
                                }
                                strokeOpacity={sombrearObjetivo ? 0.9 : 0.7}
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

/** Opacidad de las zonas en su parte más lejana del objetivo. */
const OPACIDAD_ZONA = 0.16;

/** Parte de cada zona, junto al objetivo, que queda sin color (la franja blanca del medio). */
const FRANJA_BLANCA = 0.35;

/**
 * Degradados del sombreado, por serie: uno horizontal verde/rojo para la línea del
 * objetivo y uno vertical por zona (encima y debajo del objetivo). El horizontal va en
 * píxeles, del primer al último periodo con objetivo: una línea plana no tiene alto y el
 * SVG no le pinta un degradado relativo a su caja.
 */
function DegradadosObjetivo({
    idGradiente,
    series,
    cortesPorSerie,
    datos,
}: {
    idGradiente: string;
    series: SerieGrafico[];
    cortesPorSerie: { offset: number; color: string }[][];
    datos: Punto[];
}) {
    const escalaX = useXAxisScale();

    return (
        <defs>
            {series.map((serie, posicion) => {
                const clave = claveObjetivo(serie.clave);
                const conObjetivo = datos.filter(
                    (dato) => dato[clave] !== null,
                );
                const x1 = escalaX?.(conObjetivo[0]?.etiqueta) ?? 0;
                const x2 = escalaX?.(conObjetivo.at(-1)?.etiqueta) ?? 0;

                return (
                    <linearGradient
                        key={serie.clave}
                        id={`${idGradiente}-${serie.clave}`}
                        gradientUnits="userSpaceOnUse"
                        x1={x1}
                        y1={0}
                        // Con un solo periodo, un ancho mínimo para que tome su color.
                        x2={x2 > x1 ? x2 : x1 + 1}
                        y2={0}
                    >
                        {cortesPorSerie[posicion].map((corte, indice) => (
                            <stop
                                key={indice}
                                offset={corte.offset}
                                stopColor={corte.color}
                            />
                        ))}
                    </linearGradient>
                );
            })}
            {series.flatMap((serie) => {
                // En tiempos lo de encima del objetivo incumple; en cantidades, lo de debajo.
                const encima = serie.menorEsMejor
                    ? COLOR_NO_CUMPLE
                    : COLOR_CUMPLE;
                const debajo = serie.menorEsMejor
                    ? COLOR_CUMPLE
                    : COLOR_NO_CUMPLE;

                return [
                    // De arriba (intenso) hacia el objetivo (casi transparente).
                    <linearGradient
                        key={`encima-${serie.clave}`}
                        id={`${idGradiente}-encima-${serie.clave}`}
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0"
                            stopColor={encima}
                            stopOpacity={OPACIDAD_ZONA}
                        />
                        <stop
                            offset={1 - FRANJA_BLANCA}
                            stopColor={encima}
                            stopOpacity={0}
                        />
                        <stop offset="1" stopColor={encima} stopOpacity={0} />
                    </linearGradient>,
                    // Del objetivo (casi transparente) hacia el eje (intenso).
                    <linearGradient
                        key={`debajo-${serie.clave}`}
                        id={`${idGradiente}-debajo-${serie.clave}`}
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0" stopColor={debajo} stopOpacity={0} />
                        <stop
                            offset={FRANJA_BLANCA}
                            stopColor={debajo}
                            stopOpacity={0}
                        />
                        <stop
                            offset="1"
                            stopColor={debajo}
                            stopOpacity={OPACIDAD_ZONA}
                        />
                    </linearGradient>,
                ];
            })}
        </defs>
    );
}

/**
 * Cuánto subió o bajó frente al periodo anterior, en valor y en porcentaje: verde si
 * mejora, rojo si empeora y gris sin cambio o sin datos para comparar.
 */
export function VariacionAnterior({
    variacion,
    formato,
    titulo,
}: {
    variacion: ReturnType<typeof variacionConAnterior>;
    formato: (valor: number) => string;
    /** Al pasar el puntero: qué periodo es el anterior (ej. sus fechas). */
    titulo?: string;
}) {
    if (variacion === null) {
        return (
            <div
                className="flex items-center justify-between gap-4 text-muted-foreground"
                title={titulo}
            >
                <span>vs. periodo anterior</span>
                <span>Sin datos para comparar</span>
            </div>
        );
    }

    const { diferencia, porcentaje, mejora } = variacion;
    const flecha = diferencia > 0 ? '▲' : diferencia < 0 ? '▼' : '=';
    const signo = diferencia > 0 ? '+' : diferencia < 0 ? '−' : '';

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 font-medium',
                mejora === true && 'text-emerald-700 dark:text-emerald-400',
                mejora === false && 'text-red-700 dark:text-red-400',
                mejora === null && 'text-muted-foreground',
            )}
            title={titulo}
            data-test="globo-variacion"
        >
            <span>vs. periodo anterior</span>
            <span className="tabular-nums">
                {flecha} {signo}
                {formato(Math.abs(diferencia))}
                {porcentaje !== null
                    ? ` (${signo}${formatearPorcentaje(Math.abs(porcentaje))})`
                    : ''}
            </span>
        </div>
    );
}

/** Globo de un periodo: por cada serie, su valor, su objetivo y si cumple. */
function GloboSeries({
    punto,
    anterior,
    series,
    formato,
    nombreFilas,
}: {
    punto: Punto;
    /** El periodo anterior, para comparar; null si es el primero. */
    anterior: Punto | null;
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
                        {anterior !== null ? (
                            <VariacionAnterior
                                variacion={variacionConAnterior(
                                    valor,
                                    anterior[serie.clave] as number | null,
                                    serie.menorEsMejor,
                                )}
                                formato={formato}
                            />
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}
