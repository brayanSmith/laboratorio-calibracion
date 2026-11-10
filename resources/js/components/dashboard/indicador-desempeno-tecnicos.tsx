import { ChartLine, Gauge, Timer, Target, Users, Wrench } from 'lucide-react';
import { useMemo, useState } from 'react';
import { desempenoTecnicos } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import {
    aOpcionesDependientes,
    BloqueIndicador,
    ErrorIndicador,
    FiltroAlternativa,
    FiltroCasillas,
    FiltroRango,
    FiltroSeleccionMultiple,
    PanelFiltros,
    StatTile,
    useFiltrosConBusqueda,
    useFiltrosDependientes,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import BarraCumplimiento from '@/components/dashboard/barra-cumplimiento';
import GraficoSeries, {
    VariacionAnterior,
} from '@/components/dashboard/grafico-series';
import RegistrosResponsableModal from '@/components/dashboard/registros-responsable-modal';
import TarjetaCumplimiento from '@/components/dashboard/tarjeta-cumplimiento';
import type { SerieGrafico } from '@/components/dashboard/grafico-series';
import { Badge } from '@/components/ui/badge';
import {
    formatearCantidad,
    formatearHoras,
    rangoPorDefecto,
} from '@/lib/indicadores';
import {
    cumplimientoDelResponsable,
    resumenDeCumplimiento,
} from '@/lib/cumplimiento-responsable';
import type {
    CumplimientoCantidad,
    CumplimientoServicio,
    CumplimientoTiempo,
} from '@/lib/cumplimiento-responsable';
import {
    objetivoMensualDelPeriodo,
    sumaMensualDelPeriodo,
    variacionConAnterior,
} from '@/lib/periodos-grafico';
import { cn } from '@/lib/utils';
import type { DesempenoTecnicos } from '@/types';

type Responsable = DesempenoTecnicos['responsables'][number];

/** Del laboratorio propio (con sus técnicos) o de empresas terceras. */
type Origen = 'LOCAL' | 'TERCERO';

const ORIGENES: { value: Origen; label: string }[] = [
    { value: 'LOCAL', label: 'Local' },
    { value: 'TERCERO', label: 'Tercero' },
];

/** Periodo, origen, filtros de selección múltiple y servicios que se ven. */
type FiltrosDesempeno = {
    desde: string;
    hasta: string;
    origen: Origen;
    /** Solo en Tercero: las empresas terceras elegidas. */
    laboratorios: string[];
    /** Solo en Local: los técnicos elegidos. */
    tecnicos: string[];
    magnitudes: string[];
    tipos_equipo: string[];
    servicios: string[];
};

const SIN_OPCIONES: DesempenoTecnicos['opciones'] = {
    laboratorios: [],
    tecnicos: [],
    magnitudes: [],
    tipos_equipo: [],
};

/** Una fila del detalle: el responsable con su cumplimiento en cada servicio. */
type FilaResponsable = Responsable & {
    cumplimiento: Record<'MANTENIMIENTO' | 'CALIBRACION', CumplimientoServicio>;
};

const columnHelper = createDataTableColumnHelper<FilaResponsable>();

/** Columnas de cada servicio en el detalle, en el orden en que se muestran. */
const SERVICIOS_TABLA = [
    {
        servicio: 'MANTENIMIENTO',
        resumen: 'mantenimientos',
        promedio: 'promedio_mantenimiento',
        etiqueta: 'Mantenimientos',
        singular: 'mantenimiento',
    },
    {
        servicio: 'CALIBRACION',
        resumen: 'calibraciones',
        promedio: 'promedio_calibracion',
        etiqueta: 'Calibraciones',
        singular: 'calibración',
    },
] as const;

/** Texto de una celda sin cumplimiento (sin objetivo, o en terceros la cantidad). */
function SinCumplimiento({ motivo }: { motivo: string }) {
    return (
        <span className="text-muted-foreground" title={motivo}>
            —
        </span>
    );
}

/** Cantidad hecha frente al objetivo del técnico, como barra; "—" sin objetivo. */
function CeldaCumplimientoCantidad({
    cumplimiento,
}: {
    cumplimiento: CumplimientoCantidad | null;
}) {
    if (cumplimiento === null) {
        return <SinCumplimiento motivo="Sin objetivo" />;
    }

    return (
        <BarraCumplimiento
            porcentaje={cumplimiento.porcentaje}
            cumple={cumplimiento.porcentaje >= 100}
            detalle={`de ${formatearCantidad(cumplimiento.objetivo)}`}
        />
    );
}

/** Tiempo frente al objetivo, como barra (objetivo ÷ tiempo); "—" sin objetivo o sin tiempos. */
function CeldaCumplimientoTiempo({
    cumplimiento,
}: {
    cumplimiento: CumplimientoTiempo | null;
}) {
    if (cumplimiento === null) {
        return (
            <SinCumplimiento motivo="Sin objetivo o sin tiempos registrados" />
        );
    }

    return (
        <BarraCumplimiento
            porcentaje={cumplimiento.porcentaje}
            cumple={cumplimiento.cumple}
            detalle={`obj. ${formatearHoras(cumplimiento.objetivo)}`}
        />
    );
}

/** Cantidad, su cumplimiento, tiempo promedio y su cumplimiento de un servicio. */
function columnasDelServicio(serie: (typeof SERVICIOS_TABLA)[number]) {
    return [
        columnHelper.accessor((fila) => fila[serie.resumen].cantidad, {
            id: serie.resumen,
            header: serie.etiqueta,
            cell: (info) => (
                <span className="tabular-nums">{info.getValue()}</span>
            ),
        }),
        columnHelper.accessor(
            (fila) =>
                fila.cumplimiento[serie.servicio].cantidad?.porcentaje ?? -1,
            {
                id: `cumplimiento_${serie.resumen}`,
                header: `Cumpl. ${serie.etiqueta.toLowerCase()}`,
                cell: ({ row }) => (
                    <CeldaCumplimientoCantidad
                        cumplimiento={
                            row.original.cumplimiento[serie.servicio].cantidad
                        }
                    />
                ),
            },
        ),
        columnHelper.accessor(
            (fila) => fila[serie.resumen].promedio_horas ?? -1,
            {
                id: serie.promedio,
                header: `Prom. ${serie.singular}`,
                cell: ({ row }) =>
                    celdaHoras(row.original[serie.resumen].promedio_horas),
            },
        ),
        columnHelper.accessor(
            // Al ordenar: primero los que más holgura tienen; sin objetivo, al final.
            (fila) =>
                fila.cumplimiento[serie.servicio].tiempo?.diferencia ?? 9999,
            {
                id: `cumplimiento_tiempo_${serie.resumen}`,
                header: `Cumpl. tiempo ${serie.singular}`,
                cell: ({ row }) => (
                    <CeldaCumplimientoTiempo
                        cumplimiento={
                            row.original.cumplimiento[serie.servicio].tiempo
                        }
                    />
                ),
            },
        ),
    ];
}

/** Servicios que se pueden ver, en el orden de las casillas. */
const SERVICIOS = [
    { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
    { value: 'CALIBRACION', label: 'Calibración' },
];
/** Series de los gráficos por servicio, con sus colores (los de las tarjetas) y objetivos. */
const SERIES_SERVICIO = [
    {
        servicio: 'MANTENIMIENTO',
        clave: 'mantenimientos',
        etiqueta: 'Mantenimientos',
        color: '#0284c7',
        objetivoTiempo: 'PROMEDIO_HORAS_MANTENIMIENTO',
    },
    {
        servicio: 'CALIBRACION',
        clave: 'calibraciones',
        etiqueta: 'Calibraciones',
        color: '#7c3aed',
        objetivoTiempo: 'PROMEDIO_HORAS_CALIBRACION',
    },
] as const;

/**
 * Un total de las tarjetas frente al del periodo anterior: en cantidad, mejora si sube; en
 * tiempo promedio, si baja.
 */
function ComparacionAnterior({
    datos,
    servicio,
    medida,
}: {
    datos: DesempenoTecnicos;
    servicio: 'mantenimientos' | 'calibraciones';
    medida: 'cantidad' | 'promedio_horas';
}) {
    const anterior = datos.periodo_anterior;

    return (
        <div className="mt-1 text-xs">
            <VariacionAnterior
                variacion={variacionConAnterior(
                    datos.totales[servicio][medida],
                    anterior.totales[servicio][medida],
                    medida === 'promedio_horas',
                )}
                formato={
                    medida === 'cantidad' ? formatearCantidad : formatearHoras
                }
                titulo={`Periodo anterior: ${anterior.desde} a ${anterior.hasta}`}
            />
        </div>
    );
}

/** Celda de un tiempo promedio; sin tiempo registrado (-1 al ordenar) muestra "—". */
const celdaHoras = (horas: number | null) => (
    <span className="tabular-nums">{formatearHoras(horas)}</span>
);

/**
 * Mantenimientos y calibraciones finalizados por técnico (o empresa tercera) en el
 * periodo, con su cantidad y su tiempo promedio.
 */
export default function IndicadorDesempenoTecnicos() {
    const { borrador, setBorrador, filtros, buscar, hayCambios } =
        useFiltrosConBusqueda<FiltrosDesempeno>({
            ...rangoPorDefecto(),
            origen: 'LOCAL',
            laboratorios: [],
            tecnicos: [],
            magnitudes: [],
            tipos_equipo: [],
            servicios: SERVICIOS.map((servicio) => servicio.value),
        });
    const { datos, cargando, error, actualizar } =
        useIndicador<DesempenoTecnicos>(desempenoTecnicos, filtros);

    const cambiar = (cambios: Partial<FiltrosDesempeno>) =>
        setBorrador((actuales) => ({ ...actuales, ...cambios }));

    // Listas dependientes de selección múltiple: laboratorio → técnico → magnitud → tipo
    // de equipo (las empresas terceras no tienen técnicos).
    const opciones = useFiltrosDependientes(
        datos?.opciones,
        borrador,
        setBorrador,
        SIN_OPCIONES,
    );

    const servicios = filtros.servicios;
    const verMantenimientos = servicios.includes('MANTENIMIENTO');
    const verCalibraciones = servicios.includes('CALIBRACION');

    /**
     * Una serie por servicio marcado (mantenimiento, calibración o los dos), cada una con
     * su objetivo: el tiempo promedio (por tipo de equipo) o la cantidad (la suma de los
     * objetivos de los técnicos).
     */
    const seriesDe = (
        datosIndicador: DesempenoTecnicos,
        que: 'tiempo' | 'cantidad',
    ): SerieGrafico[] =>
        SERIES_SERVICIO.filter((serie) =>
            servicios.includes(serie.servicio),
        ).map((serie) => {
            const delServicio = datosIndicador.servicios.filter(
                (fila) => fila.tipo_servicio === serie.servicio,
            );
            const cantidadesDelServicio =
                datosIndicador.objetivos_usuarios.filter(
                    (objetivo) => objetivo.tipo === serie.servicio,
                );

            return {
                clave: serie.clave,
                etiqueta: serie.etiqueta,
                color: serie.color,
                menorEsMejor: que === 'tiempo',
                objetivo: (inicio, agrupacion) =>
                    que === 'tiempo'
                        ? // Objetivos por mes de los tipos de equipo visibles, combinados.
                          objetivoMensualDelPeriodo(
                              datosIndicador.objetivos,
                              delServicio,
                              inicio,
                              agrupacion,
                              serie.objetivoTiempo,
                          )
                        : sumaMensualDelPeriodo(
                              cantidadesDelServicio,
                              inicio,
                              agrupacion,
                          ),
                filas: delServicio.map((fila) => ({
                    fecha: fila.fecha,
                    valor: fila.horas,
                })),
            };
        });

    // Responsable cuyos registros se ven en la ventana (al tocar su fila).
    const [seleccionado, setSeleccionado] = useState<Responsable | null>(null);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('responsable', {
                    header: 'Responsable',
                    cell: ({ row }) => (
                        <span className="font-medium">
                            {row.original.responsable}
                        </span>
                    ),
                }),
                columnHelper.accessor('tipo', {
                    header: 'Tipo',
                    meta: {
                        filterOptions: [
                            { value: 'TECNICO', label: 'Técnico' },
                            { value: 'TERCERO', label: 'Tercero' },
                        ],
                    },
                    cell: ({ row }) =>
                        row.original.tipo === 'TERCERO' ? (
                            <Badge variant="secondary">Tercero</Badge>
                        ) : (
                            <Badge>Técnico</Badge>
                        ),
                }),
                // Solo las columnas de los servicios que se están viendo.
                ...SERVICIOS_TABLA.filter(
                    (serie) =>
                        (serie.servicio === 'MANTENIMIENTO' &&
                            verMantenimientos) ||
                        (serie.servicio === 'CALIBRACION' && verCalibraciones),
                ).flatMap(columnasDelServicio),
            ]),
        [verMantenimientos, verCalibraciones],
    );

    // Cada responsable con su cumplimiento frente a los objetivos del periodo filtrado.
    const filas = useMemo<FilaResponsable[]>(
        () =>
            (datos?.responsables ?? []).map((responsable) => ({
                ...responsable,
                cumplimiento: {
                    MANTENIMIENTO: cumplimientoDelResponsable(
                        datos as DesempenoTecnicos,
                        responsable,
                        'MANTENIMIENTO',
                        filtros.desde,
                        filtros.hasta,
                    ),
                    CALIBRACION: cumplimientoDelResponsable(
                        datos as DesempenoTecnicos,
                        responsable,
                        'CALIBRACION',
                        filtros.desde,
                        filtros.hasta,
                    ),
                },
            })),
        [datos, filtros.desde, filtros.hasta],
    );

    // Tarjetas de cumplimiento: una por servicio visible y la general de los visibles.
    const tarjetasCumplimiento = useMemo(() => {
        if (!datos) {
            return [];
        }

        const visibles = SERIES_SERVICIO.filter((serie) =>
            servicios.includes(serie.servicio),
        );
        const resumen = (elegidos: ('MANTENIMIENTO' | 'CALIBRACION')[]) =>
            resumenDeCumplimiento(
                datos,
                elegidos,
                filtros.desde,
                filtros.hasta,
            );

        return [
            ...visibles.map((serie) => ({
                clave: serie.clave,
                titulo:
                    serie.servicio === 'MANTENIMIENTO'
                        ? 'Avance Mantenimientos'
                        : 'Avance Calibraciones',
                icono: serie.servicio === 'MANTENIMIENTO' ? Wrench : Gauge,
                color: serie.color,
                resumen: resumen([serie.servicio]),
            })),
            {
                clave: 'general',
                titulo: 'Avance General',
                icono: Target,
                color: 'var(--primary)',
                resumen: resumen(visibles.map((serie) => serie.servicio)),
            },
        ];
    }, [datos, servicios, filtros.desde, filtros.hasta]);

    return (
        <div
            className="grid min-w-0 grid-cols-1 gap-4"
            data-test="indicador-desempeno-tecnicos"
        >
            <PanelFiltros
                onBuscar={buscar}
                onActualizar={actualizar}
                hayCambios={hayCambios}
                buscando={cargando}
            >
                <FiltroRango
                    id="desempeno-tecnicos"
                    desde={borrador.desde}
                    hasta={borrador.hasta}
                    onChange={cambiar}
                />
                <FiltroAlternativa
                    id="desempeno-origen"
                    etiqueta="Origen"
                    opciones={ORIGENES}
                    value={borrador.origen}
                    // Al cambiar de origen, lo elegido del otro ya no aplica.
                    onChange={(origen) =>
                        cambiar({ origen, laboratorios: [], tecnicos: [] })
                    }
                />
                {borrador.origen === 'LOCAL' ? (
                    <FiltroSeleccionMultiple
                        id="desempeno-tecnicos-tecnico"
                        etiqueta="Técnico"
                        value={borrador.tecnicos}
                        onChange={(tecnicos) => cambiar({ tecnicos })}
                        opciones={aOpcionesDependientes(opciones.tecnicos)}
                        plural="técnicos"
                    />
                ) : (
                    <FiltroSeleccionMultiple
                        id="desempeno-tecnicos-laboratorio"
                        etiqueta="Laboratorio tercero"
                        value={borrador.laboratorios}
                        onChange={(laboratorios) => cambiar({ laboratorios })}
                        opciones={aOpcionesDependientes(opciones.laboratorios)}
                        plural="laboratorios"
                    />
                )}
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-magnitud"
                    etiqueta="Magnitud"
                    value={borrador.magnitudes}
                    onChange={(magnitudes) => cambiar({ magnitudes })}
                    opciones={aOpcionesDependientes(opciones.magnitudes)}
                    todos="Todas"
                    plural="magnitudes"
                />
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={borrador.tipos_equipo}
                    onChange={(tipos_equipo) => cambiar({ tipos_equipo })}
                    opciones={aOpcionesDependientes(opciones.tipos_equipo)}
                    plural="tipos de equipo"
                />
                <FiltroCasillas
                    id="desempeno-servicio"
                    etiqueta="Servicios"
                    opciones={SERVICIOS}
                    value={borrador.servicios}
                    onChange={(elegidos) => cambiar({ servicios: elegidos })}
                />
            </PanelFiltros>

            {error ? (
                <ErrorIndicador onReintentar={actualizar} />
            ) : cargando || !datos ? (
                <CargandoContenido mensaje="Calculando desempeño..." />
            ) : (
                <>
                    <div
                        className={cn(
                            'grid gap-4 sm:grid-cols-2',
                            tarjetasCumplimiento.length === 3 &&
                                'lg:grid-cols-3',
                        )}
                    >
                        {tarjetasCumplimiento.map((tarjeta) => (
                            <TarjetaCumplimiento
                                key={tarjeta.clave}
                                titulo={tarjeta.titulo}
                                icono={tarjeta.icono}
                                colorBarra={tarjeta.color}
                                resumen={tarjeta.resumen}
                                testId={`desempeno-cumplimiento-${tarjeta.clave}`}
                            />
                        ))}
                    </div>

                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {verMantenimientos ? (
                            <>
                                <StatTile
                                    etiqueta="Mantenimientos Realizados"
                                    valor={String(
                                        datos.totales.mantenimientos.cantidad,
                                    )}
                                    detalle="Finalizados en el periodo"
                                    icono={Wrench}
                                    tono="sky"
                                    testId="desempeno-mantenimientos"
                                >
                                    <ComparacionAnterior
                                        datos={datos}
                                        servicio="mantenimientos"
                                        medida="cantidad"
                                    />
                                </StatTile>
                                <StatTile
                                    etiqueta="TAT Promedio Mantenimiento"
                                    valor={formatearHoras(
                                        datos.totales.mantenimientos
                                            .promedio_horas,
                                    )}
                                    detalle="Según los tiempos registrados"
                                    icono={Timer}
                                    tono="sky"
                                >
                                    <ComparacionAnterior
                                        datos={datos}
                                        servicio="mantenimientos"
                                        medida="promedio_horas"
                                    />
                                </StatTile>
                            </>
                        ) : null}
                        {verCalibraciones ? (
                            <>
                                <StatTile
                                    etiqueta="Calibraciones Realizadas"
                                    valor={String(
                                        datos.totales.calibraciones.cantidad,
                                    )}
                                    detalle="Terminadas en el periodo"
                                    icono={Gauge}
                                    tono="violet"
                                    testId="desempeno-calibraciones"
                                >
                                    <ComparacionAnterior
                                        datos={datos}
                                        servicio="calibraciones"
                                        medida="cantidad"
                                    />
                                </StatTile>
                                <StatTile
                                    etiqueta="TAT Promedio Calibración"
                                    valor={formatearHoras(
                                        datos.totales.calibraciones
                                            .promedio_horas,
                                    )}
                                    detalle="Según los tiempos registrados"
                                    icono={Timer}
                                    tono="violet"
                                >
                                    <ComparacionAnterior
                                        datos={datos}
                                        servicio="calibraciones"
                                        medida="promedio_horas"
                                    />
                                </StatTile>
                            </>
                        ) : null}
                    </div>

                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <BloqueIndicador
                            titulo="Tiempo Promedio por Periodo"
                            icono={Timer}
                        >
                            <GraficoSeries
                                series={seriesDe(datos, 'tiempo')}
                                medida="promedio"
                                desde={filtros.desde}
                                hasta={filtros.hasta}
                                formato={formatearHoras}
                                nombreFilas="servicios"
                                sombrearObjetivo
                                testId="grafico-desempeno-tiempo"
                            />
                        </BloqueIndicador>
                        <BloqueIndicador
                            titulo="Cantidad por Periodo"
                            icono={ChartLine}
                        >
                            <GraficoSeries
                                series={seriesDe(datos, 'cantidad')}
                                medida="cantidad"
                                desde={filtros.desde}
                                hasta={filtros.hasta}
                                formato={formatearCantidad}
                                nombreFilas="servicios"
                                sombrearObjetivo
                                testId="grafico-desempeno-cantidad"
                            />
                        </BloqueIndicador>
                    </div>

                    <BloqueIndicador
                        titulo="Detalle por responsable"
                        icono={Users}
                    >
                        <DataTable
                            data={filas}
                            columns={columns}
                            onFilaClick={setSeleccionado}
                            searchPlaceholder="Buscar técnico o empresa..."
                            emptyMessage="No hay servicios finalizados en este periodo."
                            rowTestId="desempeno-tecnicos-row"
                        />
                        <p className="mt-3 text-xs text-muted-foreground">
                            Los servicios sin tiempo registrado (Iniciar →
                            Finalizar) cuentan en la cantidad, pero no en el
                            promedio. El cumplimiento de cantidad compara lo
                            hecho con los objetivos del técnico en el periodo
                            (Objetivos → Por usuario); el de tiempo, su promedio
                            con el objetivo de los tipos de equipo que atendió.
                            Toca una fila para ver sus registros.
                        </p>
                    </BloqueIndicador>

                    <RegistrosResponsableModal
                        datos={datos}
                        responsable={seleccionado}
                        servicios={servicios}
                        onOpenChange={(abierta) => {
                            if (!abierta) {
                                setSeleccionado(null);
                            }
                        }}
                    />
                </>
            )}
        </div>
    );
}
