import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import CreateFabricanteModal from '@/components/fabricantes/create-fabricante-modal';
import DeleteFabricanteModal from '@/components/fabricantes/delete-fabricante-modal';
import EditFabricanteModal from '@/components/fabricantes/edit-fabricante-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/fabricantes';
import type { Fabricante } from '@/types';

type Props = {
    fabricantes: Fabricante[];
};

const columnHelper = createDataTableColumnHelper<Fabricante>();

export default function FabricantesIndex({ fabricantes }: Props) {
    const { can } = usePermissions();
    const canEdit = can('fabricantes.editar');
    const canDelete = can('fabricantes.eliminar');
    const [editingFabricanteId, setEditingFabricanteId] = useState<
        number | null
    >(null);
    const editingFabricante =
        fabricantes.find(
            (fabricante) => fabricante.id === editingFabricanteId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingFabricante, setDeletingFabricante] =
        useState<Fabricante | null>(null);
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
                columnHelper.accessor('equipos_count', {
                    header: 'Equipos',
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
                                    data-test="fabricante-edit-button"
                                    onClick={() => {
                                        setEditingFabricanteId(row.original.id);
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
                                    data-test="fabricante-delete-button"
                                    onClick={() => {
                                        setDeletingFabricante(row.original);
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
            <Head title="Fabricantes" />

            <h1 className="sr-only">Fabricantes</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Fabricantes"
                        description="Administra los fabricantes de los equipos de tu laboratorio"
                    />

                    {can('fabricantes.crear') ? (
                        <CreateFabricanteModal>
                            <Button data-test="fabricantes-new-button">
                                <Plus /> Nuevo fabricante
                            </Button>
                        </CreateFabricanteModal>
                    ) : null}
                </div>

                <DataTable
                    data={fabricantes}
                    columns={columns}
                    searchPlaceholder="Buscar fabricante..."
                    emptyMessage="Aún no has registrado fabricantes."
                    rowTestId="fabricante-row"
                />
            </div>

            <EditFabricanteModal
                fabricante={editingFabricante}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteFabricanteModal
                fabricante={deletingFabricante}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

FabricantesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Fabricantes',
            href: index(),
        },
    ],
};
