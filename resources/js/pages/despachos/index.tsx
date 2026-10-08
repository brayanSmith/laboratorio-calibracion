import { Head } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import DeleteDespachoModal from '@/components/despachos/delete-despacho-modal';
import EditDespachoModal from '@/components/despachos/edit-despacho-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/despachos';
import type { Despacho, DespachoOptions } from '@/types';

type Props = DespachoOptions & {
    despachos: Despacho[];
};

const columnHelper = createDataTableColumnHelper<Despacho>();

export default function DespachosIndex({
    despachos,
    tecnicos,
    clientes,
    novedadesDespacho,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('despachos.editar');
    const canDelete = can('despachos.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        despachos.find((despacho) => despacho.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Despacho | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.display({
                    id: 'equipo',
                    header: 'Equipo',
                    cell: ({ row }) => (
                        <span className="font-medium">
                            {row.original.equipo.codigo} ·{' '}
                            {row.original.equipo.modelo}
                        </span>
                    ),
                }),
                columnHelper.display({
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) => row.original.equipo.cliente.nombre,
                }),
                columnHelper.accessor('tecnico_entrega_nombre', {
                    header: 'Técnico que entrega',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('cliente_recibe_nombre', {
                    header: 'Cliente que recibe',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('entrega_autorizada', {
                    header: 'Autorizada',
                    cell: (info) => (
                        <Badge
                            className={
                                info.getValue()
                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                    : '!border-transparent !bg-red-500 !text-white'
                            }
                        >
                            {info.getValue() ? 'Sí' : 'No'}
                        </Badge>
                    ),
                }),
                columnHelper.accessor('entrega_recibida', {
                    header: 'Recibida',
                    cell: (info) => (
                        <Badge
                            className={
                                info.getValue()
                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                    : '!border-transparent !bg-red-500 !text-white'
                            }
                        >
                            {info.getValue() ? 'Sí' : 'No'}
                        </Badge>
                    ),
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
                                    data-test="despacho-edit-button"
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
                                    data-test="despacho-delete-button"
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
            <Head title="Despachos" />

            <h1 className="sr-only">Despachos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Despachos"
                    description="Despachos creados al finalizar una calibración, con quién entrega y recibe cada equipo"
                />

                <DataTable
                    data={despachos}
                    columns={columns}
                    searchPlaceholder="Buscar despacho..."
                    emptyMessage="Aún no hay despachos."
                    rowTestId="despacho-row"
                />
            </div>

            <EditDespachoModal
                despacho={editing}
                tecnicos={tecnicos}
                clientes={clientes}
                novedadesDespacho={novedadesDespacho}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteDespachoModal
                despacho={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

DespachosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Despachos',
            href: index(),
        },
    ],
};
