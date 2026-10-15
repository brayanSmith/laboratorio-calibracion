import { Head } from '@inertiajs/react';
import { Eye, Pencil, Play, Printer, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { certificado } from '@/actions/App/Http/Controllers/CalibracionController';
import DeleteCalibracionModal from '@/components/calibraciones/delete-calibracion-modal';
import EditCalibracionModal from '@/components/calibraciones/edit-calibracion-modal';
import IniciarCalibracionModal from '@/components/calibraciones/iniciar-calibracion-modal';
import VerCalibracionModal from '@/components/calibraciones/ver-calibracion-modal';
import Heading from '@/components/heading';
import ServicioTercerosTab from '@/components/servicio-terceros/servicio-terceros-tab';
import TabCountBadge from '@/components/tab-count-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { colorEstadoCalibracion } from '@/lib/estados-calibracion';
import { index } from '@/routes/calibraciones';
import type {
    Calibracion,
    CalibracionOption,
    CalibracionOptions,
    ServicioTerceroListado,
} from '@/types';

/** Convierte un ISO string a Date, o null si no hay nada que convertir. */
function toDateOrNull(value: string | null): Date | null {
    return value ? new Date(value) : null;
}

type Props = CalibracionOptions & {
    calibraciones: Calibracion[];
    serviciosTerceros: ServicioTerceroListado[];
    empresasTerceras: CalibracionOption[];
};

const columnHelper = createDataTableColumnHelper<Calibracion>();

const estadosCalibracionLabel: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    EN_PROCESO: 'En proceso',
    FINALIZADO: 'Finalizado',
    DEVOLVER_MANTENIMIENTO: 'Devolver a mantenimiento',
};

/** Una calibración finalizada o devuelta a mantenimiento ya se realizó: no se vuelve a iniciar. */
function terminada(calibracion: Calibracion): boolean {
    return (
        calibracion.estado_calibracion === 'FINALIZADO' ||
        calibracion.estado_calibracion === 'DEVOLVER_MANTENIMIENTO'
    );
}

export default function CalibracionesIndex({
    calibraciones,
    serviciosTerceros,
    empresasTerceras,
    tecnicos,
    laboratorios,
    areas,
    procedimientos,
    novedadesCalibracion,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('calibraciones.editar');
    const canDelete = can('calibraciones.eliminar');
    const canIniciar = can('calibraciones.iniciar');
    const canPdf = can('calibraciones.pdf');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        calibraciones.find((calibracion) => calibracion.id === editingId) ??
        null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Calibracion | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [iniciandoId, setIniciandoId] = useState<number | null>(null);
    const iniciando =
        calibraciones.find((calibracion) => calibracion.id === iniciandoId) ??
        null;
    const [iniciarOpen, setIniciarOpen] = useState(false);
    const [iniciadoEn, setIniciadoEn] = useState<Date | null>(null);
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        calibraciones.find((calibracion) => calibracion.id === viendoId) ??
        null;
    const [verOpen, setVerOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.accessor(
                    (row) => `${row.equipo.codigo} ${row.equipo.modelo}`,
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <span className="font-medium">
                                {row.original.equipo.codigo} ·{' '}
                                {row.original.equipo.modelo}
                            </span>
                        ),
                    },
                ),
                columnHelper.accessor(
                    (row) => row.equipo.cliente?.nombre ?? '',
                    {
                        id: 'cliente',
                        header: 'Cliente',
                        cell: ({ row }) =>
                            row.original.equipo.cliente?.nombre ?? '—',
                    },
                ),
                columnHelper.accessor('tecnico_nombre', { header: 'Técnico' }),
                columnHelper.accessor('laboratorio_nombre', {
                    header: 'Laboratorio',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('estado_calibracion', {
                    meta: {
                        filterOptions: Object.entries(
                            estadosCalibracionLabel,
                        ).map(([value, label]) => ({ value, label })),
                    },
                    header: 'Estado',
                    cell: (info) => (
                        <Badge
                            className={colorEstadoCalibracion[info.getValue()]}
                        >
                            {estadosCalibracionLabel[info.getValue()] ??
                                info.getValue()}
                        </Badge>
                    ),
                }),
                columnHelper.accessor('resultado_calibracion', {
                    meta: {
                        filterOptions: [
                            { value: 'true', label: 'Aprobado' },
                            { value: 'false', label: 'No aprobado' },
                            { value: 'null', label: 'Sin resultado' },
                        ],
                    },
                    header: 'Resultado',
                    cell: (info) =>
                        info.getValue() === null ? (
                            <span className="text-muted-foreground">—</span>
                        ) : (
                            <Badge
                                className={
                                    info.getValue()
                                        ? '!border-transparent !bg-emerald-500 !text-white'
                                        : '!border-transparent !bg-red-500 !text-white'
                                }
                            >
                                {info.getValue() ? 'Aprobado' : 'No aprobado'}
                            </Badge>
                        ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {terminada(row.original) ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="calibracion-ver-button"
                                    onClick={() => {
                                        setViendoId(row.original.id);
                                        setVerOpen(true);
                                    }}
                                >
                                    <Eye className="h-4 w-4" /> Ver
                                </Button>
                            ) : null}
                            {canPdf &&
                            row.original.estado_calibracion === 'FINALIZADO' ? (
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={certificado.url(row.original.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                        data-test="calibracion-imprimir-button"
                                    >
                                        <Printer className="h-4 w-4" /> PDF
                                    </a>
                                </Button>
                            ) : null}
                            {canEdit &&
                            !terminada(row.original) &&
                            row.original.tiempo_servicio_inicio ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="calibracion-continuar-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setEditOpen(true);
                                    }}
                                >
                                    <Play className="h-4 w-4" /> Continuar
                                </Button>
                            ) : null}
                            {canIniciar &&
                            !terminada(row.original) &&
                            !row.original.tiempo_servicio_inicio ? (
                                <Button
                                    size="sm"
                                    className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    data-test="calibracion-iniciar-button"
                                    onClick={() => {
                                        setIniciandoId(row.original.id);
                                        setIniciarOpen(true);
                                    }}
                                >
                                    <Play className="h-4 w-4" /> Iniciar
                                </Button>
                            ) : null}
                            {canEdit ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="calibracion-edit-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setEditOpen(true);
                                    }}
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                            ) : null}
                            {canDelete ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="calibracion-delete-button"
                                    onClick={() => {
                                        setDeleting(row.original);
                                        setDeleteOpen(true);
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [canEdit, canDelete, canIniciar, canPdf],
    );

    return (
        <>
            <Head title="Calibraciones" />

            <h1 className="sr-only">Calibraciones</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Calibraciones"
                    description="Calibraciones agendadas desde Ingresos, con su técnico, estado y laboratorio"
                />

                <Tabs defaultValue="local">
                    <TabsList>
                        <TabsTrigger value="local">
                            Local
                            <TabCountBadge
                                value={
                                    calibraciones.filter(
                                        (calibracion) =>
                                            calibracion.estado_calibracion ===
                                                'PENDIENTE' ||
                                            calibracion.estado_calibracion ===
                                                'EN_PROCESO',
                                    ).length
                                }
                                label="Pendientes en local"
                                className="bg-blue-500 text-white"
                            />
                        </TabsTrigger>
                        <TabsTrigger value="tercero">
                            Tercero
                            <TabCountBadge
                                value={
                                    serviciosTerceros.filter(
                                        (servicio) =>
                                            servicio.estado_final_equipo ===
                                            null,
                                    ).length
                                }
                                label="Pendientes en tercero"
                                className="bg-amber-500 text-white"
                            />
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="local">
                        <DataTable
                            data={calibraciones}
                            columns={columns}
                            searchPlaceholder="Buscar calibración..."
                            emptyMessage="Aún no hay calibraciones agendadas."
                            rowTestId="calibracion-row"
                        />
                    </TabsContent>

                    <TabsContent value="tercero">
                        <ServicioTercerosTab
                            servicios={serviciosTerceros}
                            empresasTerceras={empresasTerceras}
                            canEdit={canEdit}
                            canDelete={canDelete}
                            canIniciar={canIniciar}
                            canFinalizar={can('calibraciones.finalizar')}
                            searchPlaceholder="Buscar servicio de tercero..."
                            emptyMessage="Aún no hay calibraciones asignadas a terceros."
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <EditCalibracionModal
                calibracion={editing}
                tecnicos={tecnicos}
                laboratorios={laboratorios}
                areas={areas}
                procedimientos={procedimientos}
                novedadesCalibracion={novedadesCalibracion}
                iniciadoEn={iniciadoEn}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteCalibracionModal
                calibracion={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <VerCalibracionModal
                calibracion={viendo}
                open={verOpen}
                onOpenChange={setVerOpen}
            />
            <IniciarCalibracionModal
                calibracion={iniciando}
                open={iniciarOpen}
                onOpenChange={setIniciarOpen}
            />
        </>
    );
}

CalibracionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Calibraciones',
            href: index(),
        },
    ],
};
