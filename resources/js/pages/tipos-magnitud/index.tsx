import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import CreateTipoMagnitudModal from '@/components/tipos-magnitud/create-tipo-magnitud-modal';
import DeleteTipoMagnitudModal from '@/components/tipos-magnitud/delete-tipo-magnitud-modal';
import EditTipoMagnitudModal from '@/components/tipos-magnitud/edit-tipo-magnitud-modal';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/tipos-magnitud';
import type { TipoMagnitud } from '@/types';

type Props = {
    tiposMagnitud: TipoMagnitud[];
};

const columnHelper = createDataTableColumnHelper<TipoMagnitud>();

export default function TiposMagnitudIndex({ tiposMagnitud }: Props) {
    const { can } = usePermissions();
    const canEdit = can('tipos-magnitud.editar');
    const canDelete = can('tipos-magnitud.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        tiposMagnitud.find((tipoMagnitud) => tipoMagnitud.id === editingId) ??
        null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<TipoMagnitud | null>(null);
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
                columnHelper.accessor('especificaciones_count', {
                    header: 'Especificaciones técnicas',
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
                                    data-test="tipo-magnitud-edit-button"
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
                                    data-test="tipo-magnitud-delete-button"
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
            <Head title="Tipos de magnitud" />

            <h1 className="sr-only">Tipos de magnitud</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Tipos de magnitud"
                        description="Clasifica las magnitudes que miden los equipos de tu laboratorio"
                    />

                    {can('tipos-magnitud.crear') ? (
                        <CreateTipoMagnitudModal>
                            <Button data-test="tipos-magnitud-new-button">
                                <Plus /> Nuevo tipo de magnitud
                            </Button>
                        </CreateTipoMagnitudModal>
                    ) : null}
                </div>

                <DataTable
                    data={tiposMagnitud}
                    columns={columns}
                    searchPlaceholder="Buscar tipo de magnitud..."
                    emptyMessage="Aún no has registrado tipos de magnitud."
                    rowTestId="tipo-magnitud-row"
                />
            </div>

            <EditTipoMagnitudModal
                tipoMagnitud={editing}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteTipoMagnitudModal
                tipoMagnitud={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

TiposMagnitudIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tipos de magnitud',
            href: index(),
        },
    ],
};
