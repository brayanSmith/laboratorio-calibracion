import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { CLASE_FILA_EQUIPO_INACTIVO } from '@/components/equipo-inactivo-badge';
import {
    CeldaEquipo,
    CeldaUbicacion,
    textoEquipo,
} from '@/components/equipos/celdas-equipo';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatearFecha } from '@/lib/fechas';
import { show } from '@/routes/portal/equipos';
import type { EquipoPortal } from '@/types';

type Props = {
    equipos: EquipoPortal[];
};

/** Cómo se muestra cada estado del equipo (los colores de la interfaz). */
const ESTADOS: Record<
    EquipoPortal['estado_vencimiento'],
    { etiqueta: string; clase: string }
> = {
    AL_DIA: { etiqueta: 'Al día', clase: '!bg-emerald-500' },
    PROXIMO_A_VENCER: { etiqueta: 'Próximo a vencer', clase: '!bg-amber-500' },
    VENCIDO: { etiqueta: 'Vencido', clase: '!bg-red-500' },
    SIN_PROGRAMACION: { etiqueta: 'Sin programación', clase: '!bg-slate-400' },
    INACTIVO: { etiqueta: 'Inactivo', clase: '!bg-slate-500' },
};

const columnHelper = createDataTableColumnHelper<EquipoPortal>();

/**
 * Los equipos del cliente (los suyos y los de sus bahías) con su estado y su próximo
 * servicio. Al tocar uno se abre su traza.
 */
export default function PortalEquipos({ equipos }: Props) {
    const resumen = useMemo(
        () => ({
            total: equipos.filter((equipo) => equipo.activo).length,
            vencidos: equipos.filter(
                (equipo) => equipo.estado_vencimiento === 'VENCIDO',
            ).length,
            proximos: equipos.filter(
                (equipo) => equipo.estado_vencimiento === 'PROXIMO_A_VENCER',
            ).length,
        }),
        [equipos],
    );

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor(
                    (row) =>
                        textoEquipo(
                            row.codigo,
                            row.tipo_equipo,
                            row.modelo,
                            row.numero_serie,
                        ),
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <CeldaEquipo
                                codigo={row.original.codigo}
                                tipo={row.original.tipo_equipo}
                                clase={row.original.clase_equipo}
                                modelo={row.original.modelo}
                                serie={row.original.numero_serie}
                                inactivo={!row.original.activo}
                            />
                        ),
                    },
                ),
                columnHelper.accessor(
                    (row) => `${row.area ?? ''} ${row.bahia ?? ''}`,
                    {
                        id: 'ubicacion',
                        header: 'Área / Bahía',
                        cell: ({ row }) => (
                            <CeldaUbicacion
                                area={row.original.area}
                                bahia={row.original.bahia}
                            />
                        ),
                    },
                ),
                columnHelper.accessor('estado_vencimiento', {
                    header: 'Estado',
                    meta: {
                        filterOptions: Object.entries(ESTADOS).map(
                            ([value, { etiqueta }]) => ({
                                value,
                                label: etiqueta,
                            }),
                        ),
                    },
                    cell: (info) => {
                        const estado = ESTADOS[info.getValue()];

                        return (
                            <Badge
                                className={`!border-transparent !text-white ${estado.clase}`}
                                data-test="portal-equipo-estado"
                            >
                                {estado.etiqueta}
                            </Badge>
                        );
                    },
                }),
                columnHelper.accessor('fecha_proximo_servicio', {
                    header: 'Próximo servicio',
                    cell: (info) =>
                        info.getValue()
                            ? formatearFecha(info.getValue()!)
                            : '—',
                }),
            ]),
        [],
    );

    return (
        <>
            <Head title="Mis equipos" />

            <div className="space-y-6">
                <Heading
                    title="Mis equipos"
                    description="El estado de tus equipos y de los de tus bahías. Toca uno para ver su historial y descargar sus certificados e informes."
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <Resumen etiqueta="Equipos activos" valor={resumen.total} />
                    <Resumen
                        etiqueta="Próximos a vencer"
                        valor={resumen.proximos}
                        clase="text-amber-600"
                    />
                    <Resumen
                        etiqueta="Vencidos"
                        valor={resumen.vencidos}
                        clase="text-red-600"
                    />
                </div>

                <div className="rounded-lg border bg-background p-4">
                    <DataTable
                        data={equipos}
                        columns={columns}
                        pageSize={15}
                        rowTestId="portal-equipo-row"
                        searchPlaceholder="Buscar equipos..."
                        emptyMessage="Todavía no tienes equipos registrados."
                        onFilaClick={(equipo) =>
                            router.visit(show(equipo.id).url)
                        }
                        claseFila={(equipo) =>
                            equipo.activo
                                ? undefined
                                : CLASE_FILA_EQUIPO_INACTIVO
                        }
                    />
                </div>
            </div>
        </>
    );
}

function Resumen({
    etiqueta,
    valor,
    clase,
}: {
    etiqueta: string;
    valor: number;
    clase?: string;
}) {
    return (
        <div className="rounded-lg border bg-background p-4">
            <p className="text-sm text-muted-foreground">{etiqueta}</p>
            <p className={`text-2xl font-semibold tabular-nums ${clase ?? ''}`}>
                {valor}
            </p>
        </div>
    );
}
