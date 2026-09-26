import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateAreaModal from '@/components/areas/create-area-modal';
import DeleteAreaModal from '@/components/areas/delete-area-modal';
import EditAreaModal from '@/components/areas/edit-area-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/areas';
import type { Area } from '@/types';

type Props = {
    areas: Area[];
};

export default function AreasIndex({ areas }: Props) {
    const { can } = usePermissions();
    const [editingAreaId, setEditingAreaId] = useState<number | null>(null);
    const editingArea = areas.find((area) => area.id === editingAreaId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingArea, setDeletingArea] = useState<Area | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title="Áreas" />

            <h1 className="sr-only">Áreas</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Áreas"
                        description="Administra las áreas donde se ubican los equipos de tu laboratorio"
                    />

                    {can('areas.crear') ? (
                        <CreateAreaModal>
                            <Button data-test="areas-new-button">
                                <Plus /> Nueva área
                            </Button>
                        </CreateAreaModal>
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
                                    Descripción
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Dirección
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Bahías
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Equipos
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {areas.map((area) => (
                                <tr key={area.id} data-test="area-row">
                                    <td className="px-4 py-3 font-medium">
                                        {area.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        {area.descripcion ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {area.direccion ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {area.bahias_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        {area.equipos_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('areas.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="area-edit-button"
                                                    onClick={() => {
                                                        setEditingAreaId(
                                                            area.id,
                                                        );
                                                        setEditOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {can('areas.eliminar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="area-delete-button"
                                                    onClick={() => {
                                                        setDeletingArea(area);
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

                            {areas.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado áreas.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </div>

            <EditAreaModal
                area={editingArea}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteAreaModal
                area={deletingArea}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

AreasIndex.layout = {
    breadcrumbs: [
        {
            title: 'Áreas',
            href: index(),
        },
    ],
};
