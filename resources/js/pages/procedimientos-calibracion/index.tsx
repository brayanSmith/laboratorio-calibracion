import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import CreateProcedimientoCalibracionModal from '@/components/procedimientos-calibracion/create-procedimiento-calibracion-modal';
import DeleteProcedimientoCalibracionModal from '@/components/procedimientos-calibracion/delete-procedimiento-calibracion-modal';
import EditProcedimientoCalibracionModal from '@/components/procedimientos-calibracion/edit-procedimiento-calibracion-modal';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/procedimientos-calibracion';
import type { ProcedimientoCalibracion } from '@/types';

type Props = {
    procedimientosCalibracion: ProcedimientoCalibracion[];
};

const columnHelper = createDataTableColumnHelper<ProcedimientoCalibracion>();

export default function ProcedimientosCalibracionIndex({
    procedimientosCalibracion,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('procedimientos-calibracion.editar');
    const canDelete = can('procedimientos-calibracion.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        procedimientosCalibracion.find(
            (procedimiento) => procedimiento.id === editingId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<ProcedimientoCalibracion | null>(
        null,
    );
    const [deleteOpen, setDeleteOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('nombre', {
                    header: 'Nombre',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('calibraciones_count', {
                    header: 'Calibraciones',
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {canEdit ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="procedimiento-calibracion-edit-button"
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
                                    data-test="procedimiento-calibracion-delete-button"
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
            <Head title="Procedimientos de calibración" />

            <h1 className="sr-only">Procedimientos de calibración</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Procedimientos de calibración"
                        description="Administra los procedimientos que usan tus calibraciones"
                    />

                    {can('procedimientos-calibracion.crear') ? (
                        <CreateProcedimientoCalibracionModal>
                            <Button data-test="procedimientos-calibracion-new-button">
                                <Plus /> Nuevo procedimiento
                            </Button>
                        </CreateProcedimientoCalibracionModal>
                    ) : null}
                </div>

                <DataTable
                    data={procedimientosCalibracion}
                    columns={columns}
                    searchPlaceholder="Buscar procedimiento..."
                    emptyMessage="Aún no has registrado procedimientos de calibración."
                    rowTestId="procedimiento-calibracion-row"
                />
            </div>

            <EditProcedimientoCalibracionModal
                procedimientoCalibracion={editing}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteProcedimientoCalibracionModal
                procedimientoCalibracion={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

ProcedimientosCalibracionIndex.layout = {
    breadcrumbs: [
        {
            title: 'Procedimientos de calibración',
            href: index(),
        },
    ],
};
