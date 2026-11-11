import {
    ChartColumn,
    ListChecks,
    Rabbit,
    Timer,
    Truck,
    Turtle,
} from 'lucide-react';
import { useMemo } from 'react';
import { tiemposAtencion } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import {
    aOpcionesDependientes,
    BloqueIndicador,
    ErrorIndicador,
    FiltroRango,
    FiltroSeleccionMultiple,
    PanelFiltros,
    StatTile,
    useFiltrosConBusqueda,
    useFiltrosDependientes,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import GraficoObjetivo from '@/components/dashboard/grafico-objetivo';
import { Badge } from '@/components/ui/badge';
import {
    formatearDias,
    rangoPorDefecto,
    tonoMapaCalor,
} from '@/lib/indicadores';
import type { TiemposAtencion } from '@/types';
import { formatearFecha } from '@/lib/fechas';

type EquipoAtendido = TiemposAtencion['equipos'][number];

/** Periodo y filtros de selección múltiple de tiempos de atención. */
type FiltrosTiempos = {
    desde: string;
    hasta: string;
    areas: string[];
    laboratorios: string[];
    magnitudes: string[];
    tipos_equipo: string[];
};

const columnHelper = createDataTableColumnHelper<EquipoAtendido>();

const SIN_OPCIONES: TiemposAtencion['opciones'] = {
    areas: [],
    laboratorios: [],
    magnitudes: [],
    tipos_equipo: [],
};

/** Fondo de la celda del mapa de calor: el tono con transparencia, legible en claro y oscuro. */
const fondoCalor = (tono: number, opacidad = 0.3) =>
    `hsl(${tono} 85% 50% / ${opacidad})`;

/**
 * Tiempo de atención de un equipo con el fondo del mapa de calor: verde el más rápido,
 * amarillo los intermedios y rojo el más lento. Sin fecha de ingreso, sin color.
 */
function CeldaMapaCalor({
    dias,
    minimo,
    maximo,
}: {
    dias: number | null;
    minimo: number;
    maximo: number;
}) {
    if (dias === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className="inline-block min-w-20 rounded-md px-2 py-1 text-center font-semibold tabular-nums"
            style={{
                backgroundColor: fondoCalor(
                    tonoMapaCalor(dias, minimo, maximo),
                ),
            }}
            data-test="tiempo-mapa-calor"
        >
            {formatearDias(dias)}
        </span>
    );
}

/** Escala del mapa de calor, con los días que corresponden a cada extremo. */
function LeyendaMapaCalor({
    minimo,
    maximo,
}: {
    minimo: number;
    maximo: number;
}) {
    return (
        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span>Más rápido ({formatearDias(minimo)})</span>
            <div
                aria-hidden="true"
                className="h-2.5 w-40 rounded-full"
                style={{
                    background: `linear-gradient(to right, ${fondoCalor(120, 0.8)}, ${fondoCalor(50, 0.8)}, ${fondoCalor(0, 0.8)})`,
                }}
            />
            <span>Más lento ({formatearDias(maximo)})</span>
        </div>
    );
}

/**
 * Equipos despachados (entrega recibida) en el periodo y el tiempo total entre su ingreso
 * y su despacho, filtrado por laboratorio y tipo de equipo.
 */
export default function IndicadorTiemposAtencion() {
    const { borrador, setBorrador, filtros, buscar, hayCambios } =
        useFiltrosConBusqueda<FiltrosTiempos>({
            ...rangoPorDefecto(),
            areas: [],
            laboratorios: [],
            magnitudes: [],
            tipos_equipo: [],
        });
    const { datos, cargando, error, actualizar } =
        useIndicador<TiemposAtencion>(tiemposAtencion, filtros);

    const cambiar = (cambios: Partial<FiltrosTiempos>) =>
        setBorrador((actuales) => ({ ...actuales, ...cambios }));

    // Listas dependientes de selección múltiple: área → laboratorio → magnitud → tipo de
    // equipo.
    const opciones = useFiltrosDependientes(
        datos?.opciones,
        borrador,
        setBorrador,
        SIN_OPCIONES,
    );

    // El mapa de calor va del más rápido (verde) al más lento (rojo) de lo que se muestra.
    const minimo = datos?.resumen.minimo_dias ?? 0;
    const maximo = datos?.resumen.maximo_dias ?? 0;

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('orden_trabajo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.accessor('equipo', {
                    header: 'Equipo',
                    cell: ({ row }) => (
                        <>
                            <span className="font-medium">
                                {row.original.equipo}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {row.original.modelo}
                            </span>
                        </>
                    ),
                }),
                columnHelper.accessor('tipo_equipo', {
                    header: 'Tipo de equipo',
                }),
                columnHelper.accessor((fila) => fila.cliente ?? '', {
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) => row.original.cliente ?? '—',
                }),
                columnHelper.accessor('laboratorio', {
                    header: 'Laboratorio',
                    cell: ({ row }) =>
                        row.original.laboratorio === 'Laboratorio propio' ? (
                            <Badge>Propio</Badge>
                        ) : (
                            <Badge variant="secondary">
                                {row.original.laboratorio}
                            </Badge>
                        ),
                }),
                columnHelper.accessor((fila) => fila.fecha_recepcion ?? '', {
                    id: 'fecha_recepcion',
                    header: 'Ingreso',
                    cell: ({ row }) => formatearFecha(row.original.fecha_recepcion),
                }),
                columnHelper.accessor('fecha_despacho', {
                    header: 'Despacho',
                }),
                // Sin fecha de ingreso (-1) queda al final al ordenar de mayor a menor.
                columnHelper.accessor((fila) => fila.dias ?? -1, {
                    id: 'dias',
                    header: 'Tiempo',
                    cell: ({ row }) => (
                        <CeldaMapaCalor
                            dias={row.original.dias}
                            minimo={minimo}
                            maximo={maximo}
                        />
                    ),
                }),
            ]),
        [minimo, maximo],
    );

    return (
        <div
            className="grid min-w-0 grid-cols-1 gap-4"
            data-test="indicador-tiempos-atencion"
        >
            <PanelFiltros
                onBuscar={buscar}
                onActualizar={actualizar}
                hayCambios={hayCambios}
                buscando={cargando}
            >
                <FiltroRango
                    id="tiempos-atencion"
                    desde={borrador.desde}
                    hasta={borrador.hasta}
                    onChange={cambiar}
                />
                <FiltroSeleccionMultiple
                    id="tiempos-atencion-area"
                    etiqueta="Área"
                    value={borrador.areas}
                    onChange={(areas) => cambiar({ areas })}
                    opciones={aOpcionesDependientes(opciones.areas)}
                    todos="Todas"
                    plural="áreas"
                />
                <FiltroSeleccionMultiple
                    id="tiempos-atencion-laboratorio"
                    etiqueta="Laboratorio"
                    value={borrador.laboratorios}
                    onChange={(laboratorios) => cambiar({ laboratorios })}
                    opciones={aOpcionesDependientes(opciones.laboratorios)}
                    plural="laboratorios"
                />
                <FiltroSeleccionMultiple
                    id="tiempos-atencion-magnitud"
                    etiqueta="Magnitud"
                    value={borrador.magnitudes}
                    onChange={(magnitudes) => cambiar({ magnitudes })}
                    opciones={aOpcionesDependientes(opciones.magnitudes)}
                    todos="Todas"
                    plural="magnitudes"
                />
                <FiltroSeleccionMultiple
                    id="tiempos-atencion-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={borrador.tipos_equipo}
                    onChange={(tipos_equipo) => cambiar({ tipos_equipo })}
                    opciones={aOpcionesDependientes(opciones.tipos_equipo)}
                    plural="tipos de equipo"
                />
            </PanelFiltros>

            {error ? (
                <ErrorIndicador onReintentar={actualizar} />
            ) : cargando || !datos ? (
                <CargandoContenido mensaje="Calculando tiempos de atención..." />
            ) : (
                <>
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            etiqueta="Equipos despachados"
                            valor={String(datos.resumen.cantidad)}
                            detalle="Con la entrega recibida en el periodo"
                            icono={Truck}
                            tono="sky"
                            testId="tiempos-atencion-cantidad"
                        />
                        <StatTile
                            etiqueta="Tiempo promedio"
                            valor={formatearDias(datos.resumen.promedio_dias)}
                            detalle={`Del ingreso al despacho, sobre ${datos.resumen.con_tiempo} de ${datos.resumen.cantidad} equipos`}
                            icono={Timer}
                            tono="violet"
                            testId="tiempos-atencion-promedio"
                        />
                        <StatTile
                            etiqueta="Más rápido"
                            valor={formatearDias(datos.resumen.minimo_dias)}
                            detalle="El menor tiempo de atención"
                            icono={Rabbit}
                            tono="emerald"
                        />
                        <StatTile
                            etiqueta="Más lento"
                            valor={formatearDias(datos.resumen.maximo_dias)}
                            detalle="El mayor tiempo de atención"
                            icono={Turtle}
                            tono="red"
                        />
                    </div>

                    <BloqueIndicador
                        titulo="Tiempo promedio por mes frente al objetivo"
                        icono={ChartColumn}
                    >
                        <GraficoObjetivo
                            etiquetaValor="Tiempo promedio"
                            filas={datos.equipos.map((equipo) => ({
                                fecha: equipo.fecha_despacho,
                                valor: equipo.dias,
                            }))}
                            desde={filtros.desde}
                            hasta={filtros.hasta}
                            nombreFilas="equipos"
                            objetivos={datos.objetivos}
                            concepto="TIEMPO_ATENCION_DIAS"
                            formato={formatearDias}
                            testId="grafico-tiempos-atencion"
                        />
                    </BloqueIndicador>

                    <BloqueIndicador
                        titulo="Detalle por equipo"
                        icono={ListChecks}
                    >
                        {datos.resumen.con_tiempo > 0 ? (
                            <LeyendaMapaCalor minimo={minimo} maximo={maximo} />
                        ) : null}
                        <DataTable
                            data={datos.equipos}
                            columns={columns}
                            searchPlaceholder="Buscar equipo, orden o cliente..."
                            emptyMessage="No hay equipos despachados en este periodo."
                            rowTestId="tiempos-atencion-row"
                        />
                    </BloqueIndicador>
                </>
            )}
        </div>
    );
}
