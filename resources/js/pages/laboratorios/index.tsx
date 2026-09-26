import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateLaboratorioModal from '@/components/laboratorios/create-laboratorio-modal';
import DeleteLaboratorioModal from '@/components/laboratorios/delete-laboratorio-modal';
import EditLaboratorioModal from '@/components/laboratorios/edit-laboratorio-modal';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/laboratorios';
import type { Laboratorio } from '@/types';

type Props = {
    laboratorios: Laboratorio[];
};

const columnHelper = createDataTableColumnHelper<Laboratorio>();

export default function LaboratoriosIndex({ laboratorios }: Props) {
    const { can } = usePermissions();
    const canEdit = can('laboratorios.editar');
    const canDelete = can('laboratorios.eliminar');
    const [editingLaboratorioId, setEditingLaboratorioId] = useState<
        number | null
    >(null);
    const editingLaboratorio =
        laboratorios.find(
            (laboratorio) => laboratorio.id === editingLaboratorioId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingLaboratorio, setDeletingLaboratorio] =
        useState<Laboratorio | null>(null);
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
                columnHelper.accessor('descripcion', {
                    header: 'Descripción',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('direccion', {
                    header: 'Dirección',
                    cell: (info) => info.getValue() ?? '—',
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
                                    data-test="laboratorio-edit-button"
                                    onClick={() => {
                                        setEditingLaboratorioId(
                                            row.original.id,
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
                                    data-test="laboratorio-delete-button"
                                    onClick={() => {
                                        setDeletingLaboratorio(row.original);
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
            <Head title="Laboratorios" />

            <h1 className="sr-only">Laboratorios</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Laboratorios"
                        description="Administra los laboratorios donde se realizan las calibraciones"
                    />

                    {can('laboratorios.crear') ? (
                        <CreateLaboratorioModal>
                            <Button data-test="laboratorios-new-button">
                                <Plus /> Nuevo laboratorio
                            </Button>
                        </CreateLaboratorioModal>
                    ) : null}
                </div>

                <DataTable
                    data={laboratorios}
                    columns={columns}
                    searchPlaceholder="Buscar laboratorio..."
                    emptyMessage="Aún no has registrado laboratorios."
                    rowTestId="laboratorio-row"
                />
            </div>

            <EditLaboratorioModal
                laboratorio={editingLaboratorio}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteLaboratorioModal
                laboratorio={deletingLaboratorio}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

LaboratoriosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Laboratorios',
            href: index(),
        },
    ],
};
