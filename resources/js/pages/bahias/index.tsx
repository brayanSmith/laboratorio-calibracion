import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import AsignarUsuariosBahiasModal from '@/components/bahias/asignar-usuarios-bahias-modal';
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
import type { Bahia, BahiaAreaOption, BahiaUsuarioOption } from '@/types';

type Props = {
    bahias: Bahia[];
    areas: BahiaAreaOption[];
    usuarios: BahiaUsuarioOption[];
};

const columnHelper = createDataTableColumnHelper<Bahia>();

export default function BahiasIndex({ bahias, areas, usuarios }: Props) {
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
                columnHelper.accessor((row) => row.usuario_ids.length, {
                    id: 'usuarios',
                    header: 'Usuarios',
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

                    <div className="flex items-center gap-2">
                        {can('bahias.asignar-usuarios') ? (
                            <AsignarUsuariosBahiasModal
                                bahias={bahias}
                                usuarios={usuarios}
                            >
                                <Button
                                    variant="outline"
                                    data-test="bahias-asignar-usuarios-button"
                                >
                                    <Users /> Asignar usuarios
                                </Button>
                            </AsignarUsuariosBahiasModal>
                        ) : null}
                        {can('bahias.crear') ? (
                            <CreateBahiaModal areas={areas}>
                                <Button data-test="bahias-new-button">
                                    <Plus /> Nueva bahía
                                </Button>
                            </CreateBahiaModal>
                        ) : null}
                    </div>
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
