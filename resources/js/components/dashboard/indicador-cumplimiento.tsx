import {
    CalendarClock,
    CheckCircle2,
    Clock,
    LayoutGrid,
    ListTodo,
    Target,
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
    FiltroCasillas,
    FiltroRango,
    FiltroSeleccionMultiple,
    Medidor,
    PanelFiltros,
    StatTile,
    tonoDePorcentaje,
    useFiltrosDependientes,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import { estadosProgramacion } from '@/components/equipos/equipo-programacion-fields';
import { formatearPorcentaje, rangoPorDefecto } from '@/lib/indicadores';
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
 * Equipos con mantenimiento preventivo programado en el periodo frente a los que ya
 * tienen su servicio finalizado, en total y por área.
 */
export default function IndicadorCumplimiento() {
    const [filtros, setFiltros] = useState<FiltrosCumplimiento>({
        ...rangoPorDefecto(),
        areas: [],
        magnitudes: [],
        tipos_equipo: [],
        tipos_mantenimiento: TIPOS_MANTENIMIENTO.map((tipo) => tipo.value),
    });
    const { datos, cargando } = useIndicador<Cumplimiento>(
        cumplimiento,
        filtros,
    );

    const cambiar = (cambios: Partial<FiltrosCumplimiento>) =>
        setFiltros((actuales) => ({ ...actuales, ...cambios }));

    // Listas dependientes de selección múltiple: área → magnitud → tipo de equipo.
    const opciones = useFiltrosDependientes(
        datos?.opciones,
        filtros,
        setFiltros,
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
        <div className="grid gap-4" data-test="indicador-cumplimiento">
            <PanelFiltros>
                <FiltroRango
                    id="cumplimiento"
                    desde={filtros.desde}
                    hasta={filtros.hasta}
                    onChange={cambiar}
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-area"
                    etiqueta="Área"
                    value={filtros.areas}
                    onChange={(areas) => cambiar({ areas })}
                    opciones={aOpcionesDependientes(opciones.areas)}
                    todos="Todas"
                    plural="áreas"
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-magnitud"
                    etiqueta="Magnitud"
                    value={filtros.magnitudes}
                    onChange={(magnitudes) => cambiar({ magnitudes })}
                    opciones={aOpcionesDependientes(opciones.magnitudes)}
                    todos="Todas"
                    plural="magnitudes"
                />
                <FiltroSeleccionMultiple
                    id="cumplimiento-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={filtros.tipos_equipo}
                    onChange={(tipos_equipo) => cambiar({ tipos_equipo })}
                    opciones={aOpcionesDependientes(opciones.tipos_equipo)}
                    plural="tipos de equipo"
                />
                <FiltroCasillas
                    id="cumplimiento-tipo-mantenimiento"
                    etiqueta="Tipo de mantenimiento"
                    opciones={TIPOS_MANTENIMIENTO}
                    value={filtros.tipos_mantenimiento}
                    onChange={(tipos_mantenimiento) =>
                        cambiar({ tipos_mantenimiento })
                    }
                />
            </PanelFiltros>

            {cargando || !datos ? (
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
                        />
                        <StatTile
                            etiqueta="Equipos atendidos"
                            valor={String(datos.resumen.atendidos)}
                            detalle="Con el servicio finalizado"
                            icono={CheckCircle2}
                            tono="emerald"
                            testId="cumplimiento-atendidos"
                        />
                        <StatTile
                            etiqueta="Equipos pendientes"
                            valor={String(datos.resumen.pendientes)}
                            detalle="Aún sin atender"
                            icono={Clock}
                            tono="amber"
                            testId="cumplimiento-pendientes-total"
                        />
                        <StatTile
                            etiqueta="Cumplimiento"
                            valor={formatearPorcentaje(
                                datos.resumen.porcentaje,
                            )}
                            icono={Target}
                            tono={tonoDePorcentaje(datos.resumen.porcentaje)}
                            testId="cumplimiento-porcentaje"
                        >
                            <Medidor
                                porcentaje={datos.resumen.porcentaje}
                                etiqueta="Cumplimiento total"
                                tono={tonoDePorcentaje(
                                    datos.resumen.porcentaje,
                                )}
                                sinTexto
                            />
                        </StatTile>
                    </div>

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
