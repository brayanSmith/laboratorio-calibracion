import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import CreateItemModal from '@/components/items/create-item-modal';
import DeleteItemModal from '@/components/items/delete-item-modal';
import EditItemModal from '@/components/items/edit-item-modal';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/items';
import type { Item } from '@/types';

type Props = {
    items: Item[];
};

const columnHelper = createDataTableColumnHelper<Item>();

export default function ItemsIndex({ items }: Props) {
    const { can } = usePermissions();
    const canEdit = can('items.editar');
    const canDelete = can('items.eliminar');
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const editingItem = items.find((item) => item.id === editingItemId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingItem, setDeletingItem] = useState<Item | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('codigo', { header: 'Código' }),
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
                columnHelper.accessor('usos_count', {
                    header: 'Usos en mantenimientos',
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
                                    data-test="item-edit-button"
                                    onClick={() => {
                                        setEditingItemId(row.original.id);
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
                                    data-test="item-delete-button"
                                    onClick={() => {
                                        setDeletingItem(row.original);
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
            <Head title="Ítems" />

            <h1 className="sr-only">Ítems</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Ítems"
                        description="Administra los ítems de consumo y repuestos de tu laboratorio"
                    />

                    {can('items.crear') ? (
                        <CreateItemModal>
                            <Button data-test="items-new-button">
                                <Plus /> Nuevo ítem
                            </Button>
                        </CreateItemModal>
                    ) : null}
                </div>

                <DataTable
                    data={items}
                    columns={columns}
                    searchPlaceholder="Buscar ítem..."
                    emptyMessage="Aún no has registrado ítems."
                    rowTestId="item-row"
                />
            </div>

            <EditItemModal
                item={editingItem}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteItemModal
                item={deletingItem}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Ítems',
            href: index(),
        },
    ],
};
