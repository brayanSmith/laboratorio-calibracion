import { Head } from '@inertiajs/react';
import { ListChecks, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import ChecklistTipoEquipoModal from '@/components/tipos-equipo/checklist-tipo-equipo-modal';
import CreateTipoEquipoModal from '@/components/tipos-equipo/create-tipo-equipo-modal';
import DeleteTipoEquipoModal from '@/components/tipos-equipo/delete-tipo-equipo-modal';
import EditTipoEquipoModal from '@/components/tipos-equipo/edit-tipo-equipo-modal';
import { tipoMantenimientoShortLabel } from '@/components/tipos-equipo/tipo-mantenimiento';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/tipos-equipo';
import type { TipoEquipo } from '@/types';

type Props = {
    tiposEquipo: TipoEquipo[];
};

export default function TiposEquipoIndex({ tiposEquipo }: Props) {
    const { can } = usePermissions();
    const [editingTipoEquipoId, setEditingTipoEquipoId] = useState<
        number | null
    >(null);
    const editingTipoEquipo =
        tiposEquipo.find(
            (tipoEquipo) => tipoEquipo.id === editingTipoEquipoId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [checklistTipoEquipoId, setChecklistTipoEquipoId] = useState<
        number | null
    >(null);
    const checklistTipoEquipo =
        tiposEquipo.find(
            (tipoEquipo) => tipoEquipo.id === checklistTipoEquipoId,
        ) ?? null;
    const [checklistOpen, setChecklistOpen] = useState(false);
    const [deletingTipoEquipo, setDeletingTipoEquipo] =
        useState<TipoEquipo | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title="Tipos de equipo" />

            <h1 className="sr-only">Tipos de equipo</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Tipos de equipo"
                        description="Clasifica los equipos de tu laboratorio por tipo"
                    />

                    {can('tipos-equipo.crear') ? (
                        <CreateTipoEquipoModal>
                            <Button data-test="tipos-equipo-new-button">
                                <Plus /> Nuevo tipo de equipo
                            </Button>
                        </CreateTipoEquipoModal>
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
                                    Tipo de mantenimiento
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Ítems de checklist
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Equipos
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {tiposEquipo.map((tipoEquipo) => (
                                <tr
                                    key={tipoEquipo.id}
                                    data-test="tipo-equipo-row"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {tipoEquipo.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {tipoMantenimientoShortLabel(
                                                tipoEquipo.tipo_mantenimiento,
                                            )}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {tipoEquipo.checklist.length}
                                    </td>
                                    <td className="px-4 py-3">
                                        {tipoEquipo.equipos_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('tipos-equipo.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="tipo-equipo-checklist-button"
                                                    onClick={() => {
                                                        setChecklistTipoEquipoId(
                                                            tipoEquipo.id,
                                                        );
                                                        setChecklistOpen(true);
                                                    }}
                                                >
                                                    <ListChecks className="h-4 w-4" />{' '}
                                                    Agregar checklist
                                                </Button>
                                            ) : null}
                                            {can('tipos-equipo.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="tipo-equipo-edit-button"
                                                    onClick={() => {
                                                        setEditingTipoEquipoId(
                                                            tipoEquipo.id,
                                                        );
                                                        setEditOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {can('tipos-equipo.eliminar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="tipo-equipo-delete-button"
                                                    onClick={() => {
                                                        setDeletingTipoEquipo(
                                                            tipoEquipo,
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

                            {tiposEquipo.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado tipos de equipo.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </div>

            <ChecklistTipoEquipoModal
                tipoEquipo={checklistTipoEquipo}
                open={checklistOpen}
                onOpenChange={setChecklistOpen}
            />
            <EditTipoEquipoModal
                tipoEquipo={editingTipoEquipo}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteTipoEquipoModal
                tipoEquipo={deletingTipoEquipo}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

TiposEquipoIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tipos de equipo',
            href: index(),
        },
    ],
};
