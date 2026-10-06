import { Head } from '@inertiajs/react';
import { Pencil, Play, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import DeleteMantenimientoModal from '@/components/mantenimientos/delete-mantenimiento-modal';
import EditMantenimientoModal from '@/components/mantenimientos/edit-mantenimiento-modal';
import GestionarMantenimientoModal from '@/components/mantenimientos/gestionar-mantenimiento-modal';
import IniciarMantenimientoModal from '@/components/mantenimientos/iniciar-mantenimiento-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { colorEstadoMantenimiento } from '@/lib/estados-mantenimiento';
import { index } from '@/routes/mantenimientos';
import type { Mantenimiento, MantenimientoOptions } from '@/types';

/** Convierte un ISO string a Date, o null si no hay nada que convertir. */
function toDateOrNull(value: string | null): Date | null {
    return value ? new Date(value) : null;
}

type Props = MantenimientoOptions & {
    mantenimientos: Mantenimiento[];
};

const columnHelper = createDataTableColumnHelper<Mantenimiento>();

const tiposMantenimientoLabel: Record<string, string> = {
    PREVENTIVO: 'Preventivo',
    CORRECTIVO: 'Correctivo',
};

const estadosMantenimientoLabel: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    EN_PROCESO: 'En proceso',
    FALTA_REPUESTOS: 'Falta repuestos',
    FINALIZADO: 'Finalizado',
};

export default function MantenimientosIndex({
    mantenimientos,
    tecnicos,
    novedadesMantenimiento,
    items,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('mantenimientos.editar');
    const canDelete = can('mantenimientos.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === editingId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Mantenimiento | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [iniciandoId, setIniciandoId] = useState<number | null>(null);
    const iniciando =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === iniciandoId,
        ) ?? null;
    const [iniciarOpen, setIniciarOpen] = useState(false);
    const [gestionandoId, setGestionandoId] = useState<number | null>(null);
    const gestionando =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === gestionandoId,
        ) ?? null;
    const [gestionarOpen, setGestionarOpen] = useState(false);
    const [iniciadoEn, setIniciadoEn] = useState<Date | null>(null);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('fecha_mantenimiento', {
                    header: 'Fecha',
                }),
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.display({
                    id: 'equipo',
                    header: 'Equipo',
                    cell: ({ row }) => (
                        <span className="font-medium">
                            {row.original.equipo.codigo} ·{' '}
                            {row.original.equipo.modelo}
                        </span>
                    ),
                }),
                columnHelper.display({
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) =>
                        row.original.equipo.cliente?.nombre ?? '—',
                }),
                columnHelper.accessor('tipo_mantenimiento', {
                    header: 'Tipo',
                    cell: (info) =>
                        tiposMantenimientoLabel[info.getValue()] ??
                        info.getValue(),
                }),
                columnHelper.accessor('tecnico_nombre', { header: 'Técnico' }),
                columnHelper.accessor('estado_mantenimiento', {
                    header: 'Estado',
                    cell: (info) => (
                        <Badge
                            className={
                                colorEstadoMantenimiento[info.getValue()]
                            }
                        >
                            {estadosMantenimientoLabel[info.getValue()] ??
                                info.getValue()}
                        </Badge>
                    ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {canEdit && row.original.tiempo_servicio_inicio ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="mantenimiento-continuar-button"
                                    onClick={() => {
                                        setGestionandoId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setGestionarOpen(true);
                                    }}
                                >
                                    <Play className="h-4 w-4" /> Continuar
                                </Button>
                            ) : null}
                            {canEdit && !row.original.tiempo_servicio_inicio ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="mantenimiento-iniciar-button"
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
                                    data-test="mantenimiento-edit-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
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
                                    data-test="mantenimiento-delete-button"
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
        [canEdit, canDelete],
    );

    return (
        <>
            <Head title="Mantenimientos" />

            <h1 className="sr-only">Mantenimientos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Mantenimientos"
                    description="Mantenimientos agendados desde Ingresos, con su técnico, estado y novedad"
                />

                <DataTable
                    data={mantenimientos}
                    columns={columns}
                    searchPlaceholder="Buscar mantenimiento..."
                    emptyMessage="Aún no hay mantenimientos agendados."
                    rowTestId="mantenimiento-row"
                />
            </div>

            <EditMantenimientoModal
                mantenimiento={editing}
                tecnicos={tecnicos}
                novedadesMantenimiento={novedadesMantenimiento}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteMantenimientoModal
                mantenimiento={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <IniciarMantenimientoModal
                mantenimiento={iniciando}
                open={iniciarOpen}
                onOpenChange={setIniciarOpen}
                onIniciado={() => {
                    setIniciadoEn(new Date());
                    setGestionandoId(iniciandoId);
                    setGestionarOpen(true);
                }}
            />
            <GestionarMantenimientoModal
                mantenimiento={gestionando}
                items={items}
                iniciadoEn={iniciadoEn}
                open={gestionarOpen}
                onOpenChange={setGestionarOpen}
            />
        </>
    );
}

MantenimientosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Mantenimientos',
            href: index(),
        },
    ],
};
