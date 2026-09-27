import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CreateUnidadMedidaModal from '@/components/unidades-medida/create-unidad-medida-modal';
import DeleteUnidadMedidaModal from '@/components/unidades-medida/delete-unidad-medida-modal';
import EditUnidadMedidaModal from '@/components/unidades-medida/edit-unidad-medida-modal';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/unidades-medida';
import type { UnidadMedida } from '@/types';

type Props = {
    unidadesMedida: UnidadMedida[];
};

const columnHelper = createDataTableColumnHelper<UnidadMedida>();

export default function UnidadesMedidaIndex({ unidadesMedida }: Props) {
    const { can } = usePermissions();
    const canEdit = can('unidades-medida.editar');
    const canDelete = can('unidades-medida.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        unidadesMedida.find((unidadMedida) => unidadMedida.id === editingId) ??
        null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<UnidadMedida | null>(null);
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
                columnHelper.accessor('simbolo', {
                    header: 'Símbolo',
                    cell: (info) => (
                        <Badge variant="secondary">{info.getValue()}</Badge>
                    ),
                }),
                columnHelper.accessor('usos_count', { header: 'Usos' }),
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
                                    data-test="unidad-medida-edit-button"
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
                                    data-test="unidad-medida-delete-button"
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
            <Head title="Unidades de medida" />

            <h1 className="sr-only">Unidades de medida</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Unidades de medida"
                        description="Administra las unidades que usan tus equipos y mediciones"
                    />

                    {can('unidades-medida.crear') ? (
                        <CreateUnidadMedidaModal>
                            <Button data-test="unidades-medida-new-button">
                                <Plus /> Nueva unidad de medida
                            </Button>
                        </CreateUnidadMedidaModal>
                    ) : null}
                </div>

                <DataTable
                    data={unidadesMedida}
                    columns={columns}
                    searchPlaceholder="Buscar unidad de medida..."
                    emptyMessage="Aún no has registrado unidades de medida."
                    rowTestId="unidad-medida-row"
                />
            </div>

            <EditUnidadMedidaModal
                unidadMedida={editing}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteUnidadMedidaModal
                unidadMedida={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

UnidadesMedidaIndex.layout = {
    breadcrumbs: [
        {
            title: 'Unidades de medida',
            href: index(),
        },
    ],
};
