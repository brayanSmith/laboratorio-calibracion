import { ChartLine, Gauge, Timer, Users, Wrench } from 'lucide-react';
import { useMemo, useState } from 'react';
import { desempenoTecnicos } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import {
    aOpcionesDependientes,
    BloqueIndicador,
    FiltroCasillas,
    FiltroRango,
    FiltroSeleccionMultiple,
    PanelFiltros,
    StatTile,
    useFiltrosDependientes,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import GraficoSeries from '@/components/dashboard/grafico-series';
import type { SerieGrafico } from '@/components/dashboard/grafico-series';
import { Badge } from '@/components/ui/badge';
import {
    formatearCantidad,
    formatearHoras,
    rangoPorDefecto,
} from '@/lib/indicadores';
import { objetivoMensualDelPeriodo } from '@/lib/periodos-grafico';
import type { DesempenoTecnicos } from '@/types';

type Responsable = DesempenoTecnicos['responsables'][number];

/** Periodo, filtros de selección múltiple y servicios que se ven. */
type FiltrosDesempeno = {
    desde: string;
    hasta: string;
    laboratorios: string[];
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

const columnHelper = createDataTableColumnHelper<Responsable>();

/** Servicios que se pueden ver, en el orden de las casillas. */
const SERVICIOS = [
    { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
    { value: 'CALIBRACION', label: 'Calibración' },
];
const COLUMNAS_MANTENIMIENTO = ['mantenimientos', 'promedio_mantenimiento'];
const COLUMNAS_CALIBRACION = ['calibraciones', 'promedio_calibracion'];
/** Series de los gráficos por servicio, con sus colores (los de las tarjetas) y objetivos. */ const SERIES_SERVICIO =
    [
        {
            servicio: 'MANTENIMIENTO',
            clave: 'mantenimientos',
            etiqueta: 'Mantenimientos',
            color: '#0284c7',
            objetivoTiempo: 'PROMEDIO_HORAS_MANTENIMIENTO',
            objetivoCantidad: 'CANTIDAD_MANTENIMIENTOS',
        },
        {
            servicio: 'CALIBRACION',
            clave: 'calibraciones',
            etiqueta: 'Calibraciones',
            color: '#7c3aed',
            objetivoTiempo: 'PROMEDIO_HORAS_CALIBRACION',
            objetivoCantidad: 'CANTIDAD_CALIBRACIONES',
        },
    ] as const;

/** Celda de un tiempo promedio; sin tiempo registrado (-1 al ordenar) muestra "—". */
const celdaHoras = (horas: number | null) => (
    <span className="tabular-nums">{formatearHoras(horas)}</span>
);

/**
 * Mantenimientos y calibraciones finalizados por técnico (o empresa tercera) en el
 * periodo, con su cantidad y su tiempo promedio.
 */
export default function IndicadorDesempenoTecnicos() {
    const [filtros, setFiltros] = useState<FiltrosDesempeno>({
        ...rangoPorDefecto(),
        laboratorios: [],
        tecnicos: [],
        magnitudes: [],
        tipos_equipo: [],
        servicios: SERVICIOS.map((servicio) => servicio.value),
    });
    const { datos, cargando } = useIndicador<DesempenoTecnicos>(
        desempenoTecnicos,
        filtros,
    );

    const cambiar = (cambios: Partial<FiltrosDesempeno>) =>
        setFiltros((actuales) => ({ ...actuales, ...cambios }));

    // Listas dependientes de selección múltiple: laboratorio → técnico → magnitud → tipo
    // de equipo (las empresas terceras no tienen técnicos).
    const opciones = useFiltrosDependientes(
        datos?.opciones,
        filtros,
        setFiltros,
        SIN_OPCIONES,
    );

    const servicios = filtros.servicios;
    const verMantenimientos = servicios.includes('MANTENIMIENTO');
    const verCalibraciones = servicios.includes('CALIBRACION');

    /**
     * Una serie por servicio marcado (mantenimiento, calibración o los dos), cada una con
     * su objetivo: el tiempo promedio o la cantidad.
     */
    const seriesDe = (
        datosIndicador: DesempenoTecnicos,
        que: 'tiempo' | 'cantidad',
    ): SerieGrafico[] =>
        SERIES_SERVICIO.filter((serie) =>
            servicios.includes(serie.servicio),
        ).map((serie) => {
            const concepto =
                que === 'tiempo'
                    ? serie.objetivoTiempo
                    : serie.objetivoCantidad;
            const delServicio = datosIndicador.servicios.filter(
                (fila) => fila.tipo_servicio === serie.servicio,
            );

            return {
                clave: serie.clave,
                etiqueta: serie.etiqueta,
                color: serie.color,
                concepto,
                // Objetivos por mes de los tipos de equipo visibles, combinados.
                objetivo: (inicio, agrupacion) =>
                    objetivoMensualDelPeriodo(
                        datosIndicador.objetivos,
                        delServicio,
                        inicio,
                        agrupacion,
                        concepto,
                    ),
                filas: delServicio.map((fila) => ({
                    fecha: fila.fecha,
                    valor: fila.horas,
                })),
            };
        });

    const columns = useMemo(
        () =>
            columnHelper
                .columns([
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
                    columnHelper.accessor(
                        (fila) => fila.mantenimientos.cantidad,
                        {
                            id: 'mantenimientos',
                            header: 'Mantenimientos',
                            cell: (info) => (
                                <span className="tabular-nums">
                                    {info.getValue()}
                                </span>
                            ),
                        },
                    ),
                    columnHelper.accessor(
                        (fila) => fila.mantenimientos.promedio_horas ?? -1,
                        {
                            id: 'promedio_mantenimiento',
                            header: 'Prom. mantenimiento',
                            cell: ({ row }) =>
                                celdaHoras(
                                    row.original.mantenimientos.promedio_horas,
                                ),
                        },
                    ),
                    columnHelper.accessor(
                        (fila) => fila.calibraciones.cantidad,
                        {
                            id: 'calibraciones',
                            header: 'Calibraciones',
                            cell: (info) => (
                                <span className="tabular-nums">
                                    {info.getValue()}
                                </span>
                            ),
                        },
                    ),
                    columnHelper.accessor(
                        (fila) => fila.calibraciones.promedio_horas ?? -1,
                        {
                            id: 'promedio_calibracion',
                            header: 'Prom. calibración',
                            cell: ({ row }) =>
                                celdaHoras(
                                    row.original.calibraciones.promedio_horas,
                                ),
                        },
                    ),
                ])
                .filter((columna) => {
                    // Solo las columnas de los servicios que se están viendo.
                    if (COLUMNAS_MANTENIMIENTO.includes(columna.id ?? '')) {
                        return verMantenimientos;
                    }

                    if (COLUMNAS_CALIBRACION.includes(columna.id ?? '')) {
                        return verCalibraciones;
                    }

                    return true;
                }),
        [verMantenimientos, verCalibraciones],
    );

    return (
        <div className="grid gap-4" data-test="indicador-desempeno-tecnicos">
            <PanelFiltros>
                <FiltroRango
                    id="desempeno-tecnicos"
                    desde={filtros.desde}
                    hasta={filtros.hasta}
                    onChange={cambiar}
                />
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-laboratorio"
                    etiqueta="Laboratorio"
                    value={filtros.laboratorios}
                    onChange={(laboratorios) => cambiar({ laboratorios })}
                    opciones={aOpcionesDependientes(opciones.laboratorios)}
                    plural="laboratorios"
                />
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-tecnico"
                    etiqueta="Técnico"
                    value={filtros.tecnicos}
                    onChange={(tecnicos) => cambiar({ tecnicos })}
                    opciones={aOpcionesDependientes(opciones.tecnicos)}
                    plural="técnicos"
                />
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-magnitud"
                    etiqueta="Magnitud"
                    value={filtros.magnitudes}
                    onChange={(magnitudes) => cambiar({ magnitudes })}
                    opciones={aOpcionesDependientes(opciones.magnitudes)}
                    todos="Todas"
                    plural="magnitudes"
                />
                <FiltroSeleccionMultiple
                    id="desempeno-tecnicos-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={filtros.tipos_equipo}
                    onChange={(tipos_equipo) => cambiar({ tipos_equipo })}
                    opciones={aOpcionesDependientes(opciones.tipos_equipo)}
                    plural="tipos de equipo"
                />
                <FiltroCasillas
                    id="desempeno-servicio"
                    etiqueta="Servicios"
                    opciones={SERVICIOS}
                    value={servicios}
                    onChange={(elegidos) => cambiar({ servicios: elegidos })}
                />
            </PanelFiltros>

            {cargando || !datos ? (
                <CargandoContenido mensaje="Calculando desempeño..." />
            ) : (
                <>
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {verMantenimientos ? (
                            <>
                                <StatTile
                                    etiqueta="Mantenimientos"
                                    valor={String(
                                        datos.totales.mantenimientos.cantidad,
                                    )}
                                    detalle="Finalizados en el periodo"
                                    icono={Wrench}
                                    tono="sky"
                                    testId="desempeno-mantenimientos"
                                />
                                <StatTile
                                    etiqueta="Tiempo promedio de mantenimiento"
                                    valor={formatearHoras(
                                        datos.totales.mantenimientos
                                            .promedio_horas,
                                    )}
                                    detalle="Según los tiempos registrados"
                                    icono={Timer}
                                    tono="sky"
                                />
                            </>
                        ) : null}
                        {verCalibraciones ? (
                            <>
                                <StatTile
                                    etiqueta="Calibraciones"
                                    valor={String(
                                        datos.totales.calibraciones.cantidad,
                                    )}
                                    detalle="Terminadas en el periodo"
                                    icono={Gauge}
                                    tono="violet"
                                    testId="desempeno-calibraciones"
                                />
                                <StatTile
                                    etiqueta="Tiempo promedio de calibración"
                                    valor={formatearHoras(
                                        datos.totales.calibraciones
                                            .promedio_horas,
                                    )}
                                    detalle="Según los tiempos registrados"
                                    icono={Timer}
                                    tono="violet"
                                />
                            </>
                        ) : null}
                    </div>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <BloqueIndicador
                            titulo="Tiempo promedio por periodo"
                            icono={Timer}
                        >
                            <GraficoSeries
                                series={seriesDe(datos, 'tiempo')}
                                medida="promedio"
                                desde={filtros.desde}
                                hasta={filtros.hasta}
                                formato={formatearHoras}
                                nombreFilas="servicios"
                                testId="grafico-desempeno-tiempo"
                            />
                        </BloqueIndicador>
                        <BloqueIndicador
                            titulo="Cantidad por periodo"
                            icono={ChartLine}
                        >
                            <GraficoSeries
                                series={seriesDe(datos, 'cantidad')}
                                medida="cantidad"
                                desde={filtros.desde}
                                hasta={filtros.hasta}
                                formato={formatearCantidad}
                                nombreFilas="servicios"
                                testId="grafico-desempeno-cantidad"
                            />
                        </BloqueIndicador>
                    </div>

                    <BloqueIndicador
                        titulo="Detalle por responsable"
                        icono={Users}
                    >
                        <DataTable
                            data={datos.responsables}
                            columns={columns}
                            searchPlaceholder="Buscar técnico o empresa..."
                            emptyMessage="No hay servicios finalizados en este periodo."
                            rowTestId="desempeno-tecnicos-row"
                        />
                        <p className="mt-3 text-xs text-muted-foreground">
                            Los servicios sin tiempo registrado (Iniciar →
                            Finalizar) cuentan en la cantidad, pero no en el
                            promedio.
                        </p>
                    </BloqueIndicador>
                </>
            )}
        </div>
    );
}
