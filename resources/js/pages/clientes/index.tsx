import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateClienteModal from '@/components/clientes/create-cliente-modal';
import DeleteClienteModal from '@/components/clientes/delete-cliente-modal';
import EditClienteModal from '@/components/clientes/edit-cliente-modal';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/clientes';
import type { Cliente } from '@/types';

type Props = {
    clientes: Cliente[];
};

const columnHelper = createDataTableColumnHelper<Cliente>();

export default function ClientesIndex({ clientes }: Props) {
    const { can } = usePermissions();
    const canEdit = can('clientes.editar');
    const canDelete = can('clientes.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        clientes.find((cliente) => cliente.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Cliente | null>(null);
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
                columnHelper.accessor('email', { header: 'Correo' }),
                columnHelper.accessor('telefono', {
                    header: 'Teléfono',
                    cell: (info) => info.getValue() ?? '—',
                }),
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
                                    data-test="cliente-edit-button"
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
                                    data-test="cliente-delete-button"
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
            <Head title="Clientes" />

            <h1 className="sr-only">Clientes</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Clientes"
                        description="Administra los clientes de tu laboratorio"
                    />

                    {can('clientes.crear') ? (
                        <CreateClienteModal>
                            <Button data-test="clientes-new-button">
                                <Plus /> Nuevo cliente
                            </Button>
                        </CreateClienteModal>
                    ) : null}
                </div>

                <DataTable
                    data={clientes}
                    columns={columns}
                    searchPlaceholder="Buscar cliente..."
                    emptyMessage="Aún no has registrado clientes."
                    rowTestId="cliente-row"
                />
            </div>

            <EditClienteModal
                cliente={editing}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteClienteModal
                cliente={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

ClientesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Clientes',
            href: index(),
        },
    ],
};
