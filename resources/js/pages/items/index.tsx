import { Head } from '@inertiajs/react';
import {
    History,
    PackageCheck,
    PackagePlus,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import CreateEntradaItemModal from '@/components/items/create-entrada-item-modal';
import CreateItemModal from '@/components/items/create-item-modal';
import DeleteItemModal from '@/components/items/delete-item-modal';
import EditItemModal from '@/components/items/edit-item-modal';
import EntradasPendientesModal from '@/components/items/entradas-pendientes-modal';
import MovimientosItemModal from '@/components/items/movimientos-item-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/items';
import type { Item } from '@/types';

type Props = {
    items: Item[];
};

const columnHelper = createDataTableColumnHelper<Item>();

const STOCK_BAJO = 5;

function estadoStock(stock: number): { etiqueta: string; clases: string } {
    if (stock <= 0) {
        return {
            etiqueta: 'Agotado',
            clases: 'border-transparent bg-red-700 text-white',
        };
    }

    if (stock <= STOCK_BAJO) {
        return {
            etiqueta: 'Bajo',
            clases: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    return {
        etiqueta: 'Disponible',
        clases: 'border-transparent bg-emerald-700 text-white',
    };
}

export default function ItemsIndex({ items }: Props) {
    const { can } = usePermissions();
    const canEdit = can('items.editar');
    const canDelete = can('items.eliminar');
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const editingItem = items.find((item) => item.id === editingItemId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingItem, setDeletingItem] = useState<Item | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [entradaItemId, setEntradaItemId] = useState<number | null>(null);
    const entradaItem = items.find((item) => item.id === entradaItemId) ?? null;
    const [entradaOpen, setEntradaOpen] = useState(false);
    const [pendientesItemId, setPendientesItemId] = useState<number | null>(
        null,
    );
    const pendientesItem =
        items.find((item) => item.id === pendientesItemId) ?? null;
    const [pendientesOpen, setPendientesOpen] = useState(false);
    const [movimientosItemId, setMovimientosItemId] = useState<number | null>(
        null,
    );
    const movimientosItem =
        items.find((item) => item.id === movimientosItemId) ?? null;
    const [movimientosOpen, setMovimientosOpen] = useState(false);

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
                columnHelper.accessor('ubicacion', {
                    header: 'Ubicación',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('precio_unitario', {
                    header: 'Precio unitario',
                    cell: (info) =>
                        info.getValue() === null
                            ? '—'
                            : info.getValue()!.toLocaleString('es-CO', {
                                  style: 'currency',
                                  currency: 'COP',
                              }),
                }),
                columnHelper.accessor('entradas', { header: 'Entradas' }),
                columnHelper.accessor('salidas', { header: 'Salidas' }),
                columnHelper.accessor('stock', {
                    header: 'Stock',
                    cell: (info) => {
                        const { etiqueta, clases } = estadoStock(
                            info.getValue(),
                        );

                        return (
                            <Badge
                                variant="outline"
                                className={`${clases} px-2.5 py-1 tabular-nums`}
                                data-test="item-stock-badge"
                                title={etiqueta}
                            >
                                {info.getValue()}
                            </Badge>
                        );
                    },
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                title="Ver entradas y salidas"
                                data-test="item-movimientos-button"
                                onClick={() => {
                                    setMovimientosItemId(row.original.id);
                                    setMovimientosOpen(true);
                                }}
                            >
                                <History className="h-4 w-4" />
                            </Button>
                            {canEdit ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Registrar entrada"
                                    data-test="item-entrada-button"
                                    onClick={() => {
                                        setEntradaItemId(row.original.id);
                                        setEntradaOpen(true);
                                    }}
                                >
                                    <PackagePlus className="h-4 w-4" />
                                </Button>
                            ) : null}
                            {canEdit &&
                            row.original.entradas_pendientes.length > 0 ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Entradas pendientes de recibir"
                                    data-test="item-pendientes-button"
                                    onClick={() => {
                                        setPendientesItemId(row.original.id);
                                        setPendientesOpen(true);
                                    }}
                                >
                                    <PackageCheck className="h-4 w-4" />
                                    <span className="text-xs tabular-nums">
                                        {
                                            row.original.entradas_pendientes
                                                .length
                                        }
                                    </span>
                                </Button>
                            ) : null}
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
            <MovimientosItemModal
                item={movimientosItem}
                open={movimientosOpen}
                onOpenChange={setMovimientosOpen}
            />
            <EntradasPendientesModal
                item={pendientesItem}
                open={pendientesOpen}
                onOpenChange={setPendientesOpen}
            />
            <CreateEntradaItemModal
                item={entradaItem}
                open={entradaOpen}
                onOpenChange={setEntradaOpen}
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
