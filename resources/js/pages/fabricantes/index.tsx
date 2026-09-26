import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateFabricanteModal from '@/components/fabricantes/create-fabricante-modal';
import DeleteFabricanteModal from '@/components/fabricantes/delete-fabricante-modal';
import EditFabricanteModal from '@/components/fabricantes/edit-fabricante-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/fabricantes';
import type { Fabricante } from '@/types';

type Props = {
    fabricantes: Fabricante[];
};

export default function FabricantesIndex({ fabricantes }: Props) {
    const { can } = usePermissions();
    const [editingFabricanteId, setEditingFabricanteId] = useState<
        number | null
    >(null);
    const editingFabricante =
        fabricantes.find(
            (fabricante) => fabricante.id === editingFabricanteId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingFabricante, setDeletingFabricante] =
        useState<Fabricante | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title="Fabricantes" />

            <h1 className="sr-only">Fabricantes</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Fabricantes"
                        description="Administra los fabricantes de los equipos de tu laboratorio"
                    />

                    {can('fabricantes.crear') ? (
                        <CreateFabricanteModal>
                            <Button data-test="fabricantes-new-button">
                                <Plus /> Nuevo fabricante
                            </Button>
                        </CreateFabricanteModal>
                    ) : null}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nombre
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Equipos
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {fabricantes.map((fabricante) => (
                                <tr
                                    key={fabricante.id}
                                    data-test="fabricante-row"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {fabricante.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        {fabricante.equipos_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('fabricantes.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="fabricante-edit-button"
                                                    onClick={() => {
                                                        setEditingFabricanteId(
                                                            fabricante.id,
                                                        );
                                                        setEditOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {can('fabricantes.eliminar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="fabricante-delete-button"
                                                    onClick={() => {
                                                        setDeletingFabricante(
                                                            fabricante,
                                                        );
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

                            {fabricantes.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado fabricantes.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </div>

            <EditFabricanteModal
                fabricante={editingFabricante}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteFabricanteModal
                fabricante={deletingFabricante}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

FabricantesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Fabricantes',
            href: index(),
        },
    ],
};
