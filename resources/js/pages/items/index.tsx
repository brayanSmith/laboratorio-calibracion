import { Head } from "@inertiajs/react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import CreateItemModal from "@/components/items/create-item-modal";
import DeleteItemModal from "@/components/items/delete-item-modal";
import EditItemModal from "@/components/items/edit-item-modal";
import Heading from "@/components/heading";
import { Button } from "@/components/ui/button";
import { usePermissions } from "@/hooks/use-permissions";
import { index } from "@/routes/items";
import type { Item } from "@/types";

type Props = {
    items: Item[];
};

export default function ItemsIndex({ items }: Props) {
    const { can } = usePermissions();
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const editingItem = items.find((item) => item.id === editingItemId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingItem, setDeletingItem] = useState<Item | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

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

                    {can("items.crear") ? (
                        <CreateItemModal>
                            <Button data-test="items-new-button">
                                <Plus /> Nuevo ítem
                            </Button>
                        </CreateItemModal>
                    ) : null}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Código
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Nombre
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Descripción
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Usos en mantenimientos
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {items.map((item) => (
                                <tr key={item.id} data-test="item-row">
                                    <td className="px-4 py-3">{item.codigo}</td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.descripcion ?? "—"}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.usos_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can("items.editar") ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="item-edit-button"
                                                    onClick={() => {
                                                        setEditingItemId(
                                                            item.id,
                                                        );
                                                        setEditOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {can("items.eliminar") ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="item-delete-button"
                                                    onClick={() => {
                                                        setDeletingItem(item);
                                                        setDeleteOpen(true);
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {items.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado ítems.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
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
            title: "Ítems",
            href: index(),
        },
    ],
};
