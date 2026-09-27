import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateBahiaModal from '@/components/bahias/create-bahia-modal';
import DeleteBahiaModal from '@/components/bahias/delete-bahia-modal';
import EditBahiaModal from '@/components/bahias/edit-bahia-modal';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/bahias';
import type { Bahia, BahiaAreaOption } from '@/types';

type Props = {
    bahias: Bahia[];
    areas: BahiaAreaOption[];
};

const columnHelper = createDataTableColumnHelper<Bahia>();

export default function BahiasIndex({ bahias, areas }: Props) {
    const { can } = usePermissions();
    const canEdit = can('bahias.editar');
    const canDelete = can('bahias.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing = bahias.find((bahia) => bahia.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Bahia | null>(null);
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
                columnHelper.accessor('area_nombre', { header: 'Área' }),
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
                                    data-test="bahia-edit-button"
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
                                    data-test="bahia-delete-button"
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
            <Head title="Bahías" />

            <h1 className="sr-only">Bahías</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Bahías"
                        description="Administra las bahías dentro de las áreas de tu laboratorio"
                    />

                    {can('bahias.crear') ? (
                        <CreateBahiaModal areas={areas}>
                            <Button data-test="bahias-new-button">
                                <Plus /> Nueva bahía
                            </Button>
                        </CreateBahiaModal>
                    ) : null}
                </div>

                <DataTable
                    data={bahias}
                    columns={columns}
                    searchPlaceholder="Buscar bahía..."
                    emptyMessage="Aún no has registrado bahías."
                    rowTestId="bahia-row"
                />
            </div>

            <EditBahiaModal
                bahia={editing}
                areas={areas}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteBahiaModal
                bahia={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

BahiasIndex.layout = {
    breadcrumbs: [
        {
            title: 'Bahías',
            href: index(),
        },
    ],
};
