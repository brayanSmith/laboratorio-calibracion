import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, CheckCircle2, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import AlertasEquipos from '@/components/portal/alertas-equipos';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatearFecha } from '@/lib/fechas';
import { show } from '@/routes/portal/equipos';
import type { EquipoPortal } from '@/types';

type Props = {
    equipos: EquipoPortal[];
};

type EstadoEquipo = EquipoPortal['estado_vencimiento'];

/** Cómo se muestra cada estado del equipo (los colores de la interfaz). */
const ESTADOS: Record<EstadoEquipo, { etiqueta: string; clase: string }> = {
    AL_DIA: { etiqueta: 'Al día', clase: '!bg-emerald-500' },
    PROXIMO_A_VENCER: { etiqueta: 'Próximo a vencer', clase: '!bg-amber-500' },
    VENCIDO: { etiqueta: 'Vencido', clase: '!bg-red-500' },
    SIN_PROGRAMACION: { etiqueta: 'Sin programación', clase: '!bg-slate-400' },
    INACTIVO: { etiqueta: 'Inactivo', clase: '!bg-slate-500' },
};

/** Las tarjetas de resumen, con los mismos colores e íconos que las del dashboard del laboratorio. */
const TARJETAS: {
    titulo: string;
    icono: LucideIcon;
    color: string;
    /** Botón en un tono más intenso que el fondo suave de la tarjeta. */
    colorBoton: string;
    testId: string;
    /** Estado por el que la tarjeta filtra la tabla; null muestra todos los equipos activos. */
    estado: EstadoEquipo | null;
}[] = [
    {
        titulo: 'Todos los equipos',
        icono: Boxes,
        color: 'border-sky-200 bg-sky-100 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        colorBoton: 'bg-sky-600 text-white hover:bg-sky-700',
        testId: 'portal-tarjeta-todos',
        estado: null,
    },
    {
        titulo: 'Equipos al día',
        icono: CheckCircle2,
        color: 'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        colorBoton: 'bg-emerald-600 text-white hover:bg-emerald-700',
        testId: 'portal-tarjeta-al-dia',
        estado: 'AL_DIA',
    },
    {
        titulo: 'Equipos próximos a vencer',
        icono: Clock,
        color: 'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        colorBoton: 'bg-amber-500 text-white hover:bg-amber-600',
        testId: 'portal-tarjeta-proximos',
        estado: 'PROXIMO_A_VENCER',
    },
    {
        titulo: 'Equipos vencidos',
        icono: AlertTriangle,
        color: 'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        colorBoton: 'bg-red-600 text-white hover:bg-red-700',
        testId: 'portal-tarjeta-vencidos',
        estado: 'VENCIDO',
    },
];

const columnHelper = createDataTableColumnHelper<EquipoPortal>();

/**
 * Los equipos del cliente (los suyos y los de sus bahías) con su estado y su próximo
 * servicio. Al tocar uno se abre su traza.
 */
export default function PortalEquipos({ equipos }: Props) {
    // Cada "Ver" vuelve a montar la tabla ya filtrada por ese estado (como en el dashboard).
    const [vista, setVista] = useState<{
        n: number;
        estado: EstadoEquipo | null;
    }>({ n: 0, estado: null });

    const conteo = (estado: EstadoEquipo | null): number =>
        equipos.filter((equipo) =>
            estado === null
                ? equipo.activo
                : equipo.estado_vencimiento === estado,
        ).length;

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

                <AlertasEquipos equipos={equipos} />

                <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {TARJETAS.map((tarjeta) => (
                        <Card
                            key={tarjeta.testId}
                            data-test={tarjeta.testId}
                            className={tarjeta.color}
                        >
                            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                <CardTitle className="text-sm font-medium">
                                    {tarjeta.titulo}
                                </CardTitle>
                                <tarjeta.icono className="h-5 w-5" />
                            </CardHeader>
                            <CardContent className="flex items-end justify-between gap-2">
                                <p className="text-3xl font-bold tabular-nums">
                                    {conteo(tarjeta.estado)}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    className={tarjeta.colorBoton}
                                    onClick={() =>
                                        setVista((actual) => ({
                                            n: actual.n + 1,
                                            estado: tarjeta.estado,
                                        }))
                                    }
                                    data-test={`${tarjeta.testId}-ver`}
                                >
                                    Ver
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="rounded-lg border bg-background p-4">
                    <DataTable
                        key={vista.n}
                        initialColumnFilters={
                            vista.estado
                                ? [
                                      {
                                          id: 'estado_vencimiento',
                                          value: vista.estado,
                                      },
                                  ]
                                : undefined
                        }
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
