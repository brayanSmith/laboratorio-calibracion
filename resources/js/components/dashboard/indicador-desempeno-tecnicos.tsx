import { Gauge, Timer, Users, Wrench } from 'lucide-react';
import { useMemo, useState } from 'react';
import { desempenoTecnicos } from '@/actions/App/Http/Controllers/IndicadoresDashboardController';
import CargandoContenido from '@/components/cargando-contenido';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import {
    aOpciones,
    BloqueIndicador,
    FiltroRango,
    FiltroSeleccion,
    PanelFiltros,
    StatTile,
    useIndicador,
} from '@/components/dashboard/indicador-comun';
import { Badge } from '@/components/ui/badge';
import { formatearHoras, rangoPorDefecto } from '@/lib/indicadores';
import type {
    DesempenoTecnicos,
    FiltrosIndicador,
    IndicadoresDashboard,
} from '@/types';

type Responsable = DesempenoTecnicos['responsables'][number];

const columnHelper = createDataTableColumnHelper<Responsable>();

/** Celda de un tiempo promedio; sin tiempo registrado (-1 al ordenar) muestra "—". */
const celdaHoras = (horas: number | null) => (
    <span className="tabular-nums">{formatearHoras(horas)}</span>
);

/**
 * Mantenimientos y calibraciones finalizados por técnico (o empresa tercera) en el
 * periodo, con su cantidad y su tiempo promedio.
 */
export default function IndicadorDesempenoTecnicos({
    indicadores,
}: {
    indicadores: IndicadoresDashboard;
}) {
    const [filtros, setFiltros] = useState<FiltrosIndicador>({
        ...rangoPorDefecto(),
        tecnico_id: '',
        laboratorio: '',
        tipo_equipo_id: '',
    });
    const { datos, cargando } = useIndicador<DesempenoTecnicos>(
        desempenoTecnicos,
        filtros,
    );

    const cambiar = (cambios: Partial<FiltrosIndicador>) =>
        setFiltros((actuales) => ({ ...actuales, ...cambios }));

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
                columnHelper.accessor((fila) => fila.mantenimientos.cantidad, {
                    id: 'mantenimientos',
                    header: 'Mantenimientos',
                    cell: (info) => (
                        <span className="tabular-nums">{info.getValue()}</span>
                    ),
                }),
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
                columnHelper.accessor((fila) => fila.calibraciones.cantidad, {
                    id: 'calibraciones',
                    header: 'Calibraciones',
                    cell: (info) => (
                        <span className="tabular-nums">{info.getValue()}</span>
                    ),
                }),
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
            ]),
        [],
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
                <FiltroSeleccion
                    id="desempeno-tecnicos-tecnico"
                    etiqueta="Técnico"
                    value={filtros.tecnico_id ?? ''}
                    onChange={(tecnico_id) => cambiar({ tecnico_id })}
                    opciones={aOpciones(indicadores.tecnicos)}
                />
                <FiltroSeleccion
                    id="desempeno-tecnicos-laboratorio"
                    etiqueta="Laboratorio"
                    value={filtros.laboratorio ?? ''}
                    onChange={(laboratorio) => cambiar({ laboratorio })}
                    opciones={[
                        { id: 'propio', label: 'Laboratorio propio' },
                        ...aOpciones(indicadores.empresasTerceras),
                    ]}
                />
                <FiltroSeleccion
                    id="desempeno-tecnicos-tipo-equipo"
                    etiqueta="Tipo de equipo"
                    value={filtros.tipo_equipo_id ?? ''}
                    onChange={(tipo_equipo_id) => cambiar({ tipo_equipo_id })}
                    opciones={aOpciones(indicadores.tiposEquipo)}
                />
            </PanelFiltros>

            {cargando || !datos ? (
                <CargandoContenido mensaje="Calculando desempeño..." />
            ) : (
                <>
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                                datos.totales.mantenimientos.promedio_horas,
                            )}
                            detalle="Según los tiempos registrados"
                            icono={Timer}
                            tono="sky"
                        />
                        <StatTile
                            etiqueta="Calibraciones"
                            valor={String(datos.totales.calibraciones.cantidad)}
                            detalle="Terminadas en el periodo"
                            icono={Gauge}
                            tono="violet"
                            testId="desempeno-calibraciones"
                        />
                        <StatTile
                            etiqueta="Tiempo promedio de calibración"
                            valor={formatearHoras(
                                datos.totales.calibraciones.promedio_horas,
                            )}
                            detalle="Según los tiempos registrados"
                            icono={Timer}
                            tono="violet"
                        />
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
