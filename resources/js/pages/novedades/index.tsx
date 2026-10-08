import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { categoriaNovedadLabel } from '@/components/novedades/categoria-novedad';
import CreateNovedadModal from '@/components/novedades/create-novedad-modal';
import DeleteNovedadModal from '@/components/novedades/delete-novedad-modal';
import EditNovedadModal from '@/components/novedades/edit-novedad-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/novedades';
import type { Novedad } from '@/types';

type Props = {
    novedades: Novedad[];
};

const columnHelper = createDataTableColumnHelper<Novedad>();

export default function NovedadesIndex({ novedades }: Props) {
    const { can } = usePermissions();
    const canEdit = can('novedades.editar');
    const canDelete = can('novedades.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        novedades.find((novedad) => novedad.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Novedad | null>(null);
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
                columnHelper.accessor('categoria', {
                    header: 'Categoría',
                    cell: (info) => (
                        <Badge variant="secondary">
                            {categoriaNovedadLabel(info.getValue())}
                        </Badge>
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
                                    data-test="novedad-edit-button"
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
                                    data-test="novedad-delete-button"
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
            <Head title="Novedades" />

            <h1 className="sr-only">Novedades</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Novedades"
                        description="Administra las novedades que se reportan en ingresos, mantenimientos, calibraciones y salidas"
                    />

                    {can('novedades.crear') ? (
                        <CreateNovedadModal>
                            <Button data-test="novedades-new-button">
                                <Plus /> Nueva novedad
                            </Button>
                        </CreateNovedadModal>
                    ) : null}
                </div>

                <DataTable
                    data={novedades}
                    columns={columns}
                    searchPlaceholder="Buscar novedad..."
                    emptyMessage="Aún no has registrado novedades."
                    rowTestId="novedad-row"
                />
            </div>

            <EditNovedadModal
                novedad={editing}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteNovedadModal
                novedad={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

NovedadesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Novedades',
            href: index(),
        },
    ],
};
