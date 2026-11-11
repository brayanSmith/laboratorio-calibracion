import {
    CalendarClock,
    ChartColumn,
    CheckCircle2,
    ChevronDown,
    Clock,
    Gauge,
    LayoutGrid,
    ListTodo,
    MessageSquareWarning,
    PackageX,
    Target,
    TriangleAlert,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { cumplimiento } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import {
    aOpcionesDependientes,
    BloqueIndicador,
    ErrorIndicador,
    FiltroCasillas,
    FiltroRango,
    FiltroSeleccionMultiple,
    Medidor,
    PanelFiltros,
    StatTile,
    TarjetaProgresoCircular,
    tonoDePorcentaje,
    useFiltrosConBusqueda,
    useFiltrosDependientes,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import GraficoCumplimiento from '@/components/dashboard/grafico-cumplimiento';
import {
    CeldaCriticidad,
    filtroCriticidad,
    NovedadesNoRecibidos,
    ordenarPorCriticidad,
    TablaNoRecibidos,
} from '@/components/dashboard/no-recibidos-cumplimiento';
import { estadosProgramacion } from '@/components/equipos/equipo-programacion-fields';
import { rangoPorDefecto } from '@/lib/indicadores';
import { cn } from '@/lib/utils';
import type { Cumplimiento } from '@/types';

type Pendiente = Cumplimiento['pendientes'][number];

/** Periodo, filtros de selección múltiple y tipos de mantenimiento que se ven. */
type FiltrosCumplimiento = {
    desde: string;
    hasta: string;
    areas: string[];
    magnitudes: string[];
    tipos_equipo: string[];
    tipos_mantenimiento: string[];
};

/** Tipo de mantenimiento del tipo de equipo. */
const TIPOS_MANTENIMIENTO = [
    { value: 'A', label: 'Tipo A' },
    { value: 'B', label: 'Tipo B' },
];

const SIN_OPCIONES: Cumplimiento['opciones'] = {
    areas: [],
    magnitudes: [],
    tipos_equipo: [],
};

const columnHelper = createDataTableColumnHelper<Pendiente>();

/**
 * Cuántos equipos quedaron fuera de servicio en el periodo (por la fecha del servicio) y,
 * con "Ver más", en qué servicio: mantenimiento o calibración (propios o de terceros).
 */
function FueraDeServicio({ resumen }: { resumen: Cumplimiento['resumen'] }) {
    const [abierto, setAbierto] = useState(false);
    const porServicio = resumen.fuera_de_servicio_por_servicio;

    return (
        <div
            className="grid gap-1 text-xs"
            data-test="cumplimiento-fuera-de-servicio"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="inline-flex items-center gap-1 font-medium">
                    <TriangleAlert className="h-3.5 w-3.5" />
                    {resumen.fuera_de_servicio === 1
                        ? '1 quedó fuera de servicio'
                        : `${resumen.fuera_de_servicio} quedaron fuera de servicio`}
                </span>
                <button
                    type="button"
                    className="inline-flex items-center gap-0.5 font-medium underline underline-offset-2"
                    aria-expanded={abierto}
                    onClick={() => setAbierto((actual) => !actual)}
                    data-test="cumplimiento-fuera-de-servicio-ver-mas"
                >
                    {abierto ? 'Ver menos' : 'Ver más'}
                    <ChevronDown
                        className={cn(
                            'h-3.5 w-3.5 transition-transform',
                            abierto && 'rotate-180',
                        )}
                    />
                </button>
            </div>
            {abierto ? (
                <div
                    className="grid gap-1 rounded-md bg-background/60 px-2 py-1.5 text-foreground"
                    data-test="cumplimiento-fuera-de-servicio-detalle"
                >
                    <span className="text-[11px] text-muted-foreground">
                        Por la fecha del mantenimiento o de la calibración
                    </span>
                    <span className="flex items-center justify-between gap-2">
                        <span className="inline-flex items-center gap-1">
                            <Wrench className="h-3.5 w-3.5" />
                            En mantenimiento
                        </span>
                        <strong className="tabular-nums">
                            {porServicio.MANTENIMIENTO}
                        </strong>
                    </span>
                    <span className="flex items-center justify-between gap-2">
                        <span className="inline-flex items-center gap-1">
                            <Gauge className="h-3.5 w-3.5" />
                            En calibración
                        </span>
                        <strong className="tabular-nums">
                            {porServicio.CALIBRACION}
                        </strong>
                    </span>
                </div>
            ) : null}
        </div>
    );
}

/**
 * De dónde son los atendidos o los pendientes: en qué servicio va cada equipo (cuenta una
 * sola vez, así que suman el total de la tarjeta).
 */
function DesgloseServicios({
    porServicio,
    clave,
    testId,
}: {
    porServicio: Cumplimiento['resumen']['por_servicio'];
    /** Programados: los atendidos más los pendientes de cada servicio. */
    clave: 'programados' | 'atendidos' | 'pendientes';
    testId: string;
}) {
    const cuantos = (servicio: keyof typeof porServicio) =>
        clave === 'programados'
            ? porServicio[servicio].atendidos + porServicio[servicio].pendientes
            : porServicio[servicio][clave];

    return (
        <div
            className="flex flex-wrap gap-x-3 gap-y-1 text-xs"
            data-test={testId}
            title="Cada equipo cuenta una vez, en el servicio en que va: en calibración si su orden ya pasó a calibración (o solo tiene calibración programada); si no, en mantenimiento"
        >
            <span className="inline-flex items-center gap-1">
                <Wrench className="h-3.5 w-3.5" />
                Mantenimientos:{' '}
                <strong className="tabular-nums">
                    {cuantos('MANTENIMIENTO')}
                </strong>
            </span>
            <span className="inline-flex items-center gap-1">
                <Gauge className="h-3.5 w-3.5" />
                Calibraciones:{' '}
                <strong className="tabular-nums">
                    {cuantos('CALIBRACION')}
                </strong>
            </span>
        </div>
    );
}

/**
 * Equipos con mantenimiento preventivo programado en el periodo frente a los que ya
 * tienen su servicio finalizado, en total y por área.
 */
export default function IndicadorCumplimiento() {
    const { borrador, setBorrador, filtros, buscar, hayCambios } =
        useFiltrosConBusqueda<FiltrosCumplimiento>({
            ...rangoPorDefecto(),
            areas: [],
            magnitudes: [],
            tipos_equipo: [],
            tipos_mantenimiento: TIPOS_MANTENIMIENTO.map((tipo) => tipo.value),
        });
    const { datos, cargando, error, actualizar } =
        useIndicador<Cumplimiento>(cumplimiento, filtros);

    const cambiar = (cambios: Partial<FiltrosCumplimiento>) =>
        setBorrador((actuales) => ({ ...actuales, ...cambios }));

    // Listas dependientes de selección múltiple: área → magnitud → tipo de equipo.
    const opciones = useFiltrosDependientes(
        datos?.opciones,
        borrador,
        setBorrador,
        SIN_OPCIONES,
    );

    const columns = useMemo(
        () =>
            columnHelper.columns([
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
                columnHelper.accessor('estado_programacion', {
                    header: 'Estado',
                    meta: {
                        filterOptions: estadosProgramacion.map((estado) => ({
                            value: estado.value,
                            label: estado.label,
                        })),
                    },
                    cell: ({ row }) =>
                        estadosProgramacion.find(
                            (estado) =>
                                estado.value ===
                                row.original.estado_programacion,
                        )?.label ?? row.original.estado_programacion,
                }),
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                }),
            ]),
        [],
    );

    return (
        <div
            className="grid min-w-0 grid-cols-1 gap-4"
            data-test="indicador-cumplimiento"
        >
            <PanelFiltros
                onBuscar={buscar}
                onActualizar={actualizar}
                hayCambios={hayCambios}
                buscando={cargando}
            >
                <FiltroRango
                    id="cumplimiento"
                    desde={borrador.desde}
                    hasta={borrador.hasta}
                    onChange={cambiar}
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-area"
                    etiqueta="Área"
                    value={borrador.areas}
                    onChange={(areas) => cambiar({ areas })}
                    opciones={aOpcionesDependientes(opciones.areas)}
                    todos="Todas"
                    plural="áreas"
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-magnitud"
                    etiqueta="Magnitud"
                    value={borrador.magnitudes}
                    onChange={(magnitudes) => cambiar({ magnitudes })}
                    opciones={aOpcionesDependientes(opciones.magnitudes)}
                    todos="Todas"
                    plural="magnitudes"
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={borrador.tipos_equipo}
                    onChange={(tipos_equipo) => cambiar({ tipos_equipo })}
                    opciones={aOpcionesDependientes(opciones.tipos_equipo)}
                    plural="tipos de equipo"
                />
                <FiltroCasillas
                    id="cumplimiento-tipo-mantenimiento"
                    etiqueta="Tipo de mantenimiento"
                    opciones={TIPOS_MANTENIMIENTO}
                    value={borrador.tipos_mantenimiento}
                    onChange={(tipos_mantenimiento) =>
                        cambiar({ tipos_mantenimiento })
                    }
                />
            </PanelFiltros>

            {error ? (
                <ErrorIndicador onReintentar={actualizar} />
            ) : cargando || !datos ? (
                <CargandoContenido mensaje="Calculando cumplimiento..." />
            ) : (
                <>
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            etiqueta="Equipos programados"
                            valor={String(datos.resumen.programados)}
                            detalle="Con preventivo en el periodo"
                            icono={CalendarClock}
                            tono="sky"
                            testId="cumplimiento-programados"
                        >
                            <DesgloseServicios
                                porServicio={datos.resumen.por_servicio}
                                clave="programados"
                                testId="cumplimiento-programados-por-servicio"
                            />
                        </StatTile>
                        <StatTile
                            etiqueta="Equipos atendidos"
                            valor={String(datos.resumen.atendidos)}
                            detalle="Con el servicio finalizado"
                            icono={CheckCircle2}
                            tono="emerald"
                            testId="cumplimiento-atendidos"
                        >
                            <DesgloseServicios
                                porServicio={datos.resumen.por_servicio}
                                clave="atendidos"
                                testId="cumplimiento-atendidos-por-servicio"
                            />
                            <FueraDeServicio resumen={datos.resumen} />
                        </StatTile>
                        <StatTile
                            etiqueta="Equipos pendientes"
                            valor={String(datos.resumen.pendientes)}
                            detalle="Aún sin atender"
                            icono={Clock}
                            tono="amber"
                            testId="cumplimiento-pendientes-total"
                        >
                            <DesgloseServicios
                                porServicio={datos.resumen.por_servicio}
                                clave="pendientes"
                                testId="cumplimiento-pendientes-por-servicio"
                            />
                        </StatTile>
                        <TarjetaProgresoCircular
                            etiqueta="Cumplimiento"
                            porcentaje={datos.resumen.porcentaje}
                            icono={Target}
                            tono={tonoDePorcentaje(datos.resumen.porcentaje)}
                            testId="cumplimiento-porcentaje"
                            detalle={
                                <span className="opacity-80">
                                    {datos.resumen.atendidos} de{' '}
                                    {datos.resumen.programados} equipos
                                    atendidos
                                </span>
                            }
                        />
                    </div>

                    <BloqueIndicador
                        titulo="Programados, atendidos y cumplimiento"
                        icono={ChartColumn}
                    >
                        <GraficoCumplimiento
                            equipos={datos.por_fecha}
                            desde={filtros.desde}
                            hasta={filtros.hasta}
                            objetivos={datos.objetivos}
                        />
                    </BloqueIndicador>

                    {datos.por_area.length > 0 ? (
                        <BloqueIndicador
                            titulo="Cumplimiento por área"
                            icono={LayoutGrid}
                        >
                            <div className="grid gap-3">
                                {datos.por_area.map((area) => (
                                    <div
                                        key={area.area}
                                        className="grid items-center gap-1 sm:grid-cols-[minmax(0,14rem)_6rem_1fr] sm:gap-3"
                                    >
                                        <span className="truncate text-sm font-medium">
                                            {area.area}
                                        </span>
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {area.atendidos} de{' '}
                                            {area.programados}
                                        </span>
                                        <Medidor
                                            porcentaje={area.porcentaje}
                                            etiqueta={`Cumplimiento de ${area.area}`}
                                            tono={tonoDePorcentaje(
                                                area.porcentaje,
                                            )}
                                        />
                                    </div>
                                ))}
                            </div>
                        </BloqueIndicador>
                    ) : null}

                    <BloqueIndicador
                        titulo="Novedades de los equipos no recibidos"
                        icono={MessageSquareWarning}
                    >
                        <NovedadesNoRecibidos
                            novedades={datos.novedades_no_recibidos}
                        />
                    </BloqueIndicador>

                    <BloqueIndicador
                        titulo="Equipos no recibidos en el ingreso"
                        icono={PackageX}
                    >
                        <TablaNoRecibidos noRecibidos={datos.no_recibidos} />
                    </BloqueIndicador>

                    <BloqueIndicador
                        titulo="Equipos pendientes"
                        icono={ListTodo}
                    >
                        <DataTable
                            data={datos.pendientes}
                            columns={columns}
                            searchPlaceholder="Buscar equipo, tipo o área..."
                            emptyMessage="Todos los equipos programados fueron atendidos."
                            rowTestId="cumplimiento-pendiente-row"
                        />
                    </BloqueIndicador>
                </>
            )}
        </div>
    );
}
