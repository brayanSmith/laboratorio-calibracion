import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateAreaModal from '@/components/areas/create-area-modal';
import DeleteAreaModal from '@/components/areas/delete-area-modal';
import EditAreaModal from '@/components/areas/edit-area-modal';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/areas';
import type { Area } from '@/types';

type Props = {
    areas: Area[];
};

const columnHelper = createDataTableColumnHelper<Area>();

export default function AreasIndex({ areas }: Props) {
    const { can } = usePermissions();
    const canEdit = can('areas.editar');
    const canDelete = can('areas.eliminar');
    const [editingAreaId, setEditingAreaId] = useState<number | null>(null);
    const editingArea = areas.find((area) => area.id === editingAreaId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingArea, setDeletingArea] = useState<Area | null>(null);
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
                columnHelper.accessor('bahias_count', { header: 'Bahías' }),
                columnHelper.accessor('equipos_count', { header: 'Equipos' }),
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
                                    data-test="area-edit-button"
                                    onClick={() => {
                                        setEditingAreaId(row.original.id);
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
                                    data-test="area-delete-button"
                                    onClick={() => {
                                        setDeletingArea(row.original);
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
            <Head title="Áreas" />

            <h1 className="sr-only">Áreas</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Áreas"
                        description="Administra las áreas donde se ubican los equipos de tu laboratorio"
                    />

                    {can('areas.crear') ? (
                        <CreateAreaModal>
                            <Button data-test="areas-new-button">
                                <Plus /> Nueva área
                            </Button>
                        </CreateAreaModal>
                    ) : null}
                </div>

                <DataTable
                    data={areas}
                    columns={columns}
                    searchPlaceholder="Buscar área..."
                    emptyMessage="Aún no has registrado áreas."
                    rowTestId="area-row"
                />
            </div>

            <EditAreaModal
                area={editingArea}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteAreaModal
                area={deletingArea}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

AreasIndex.layout = {
    breadcrumbs: [
        {
            title: 'Áreas',
            href: index(),
        },
    ],
};
