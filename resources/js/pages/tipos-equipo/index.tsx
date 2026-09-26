import { Head } from '@inertiajs/react';
import { ListChecks, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
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

const columnHelper = createDataTableColumnHelper<TipoEquipo>();

export default function TiposEquipoIndex({ tiposEquipo }: Props) {
    const { can } = usePermissions();
    const canEdit = can('tipos-equipo.editar');
    const canDelete = can('tipos-equipo.eliminar');
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

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('nombre', {
                    header: 'Nombre',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('tipo_mantenimiento', {
                    header: 'Tipo de mantenimiento',
                    cell: (info) => (
                        <Badge variant="secondary">
                            {tipoMantenimientoShortLabel(info.getValue())}
                        </Badge>
                    ),
                }),
                columnHelper.accessor(
                    (tipoEquipo) => tipoEquipo.checklist.length,
                    {
                        id: 'checklist',
                        header: 'Ítems de checklist',
                    },
                ),
                columnHelper.accessor('equipos_count', { header: 'Equipos' }),
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
                                    data-test="tipo-equipo-checklist-button"
                                    onClick={() => {
                                        setChecklistTipoEquipoId(
                                            row.original.id,
                                        );
                                        setChecklistOpen(true);
                                    }}
                                >
                                    <ListChecks className="h-4 w-4" /> Agregar
                                    checklist
                                </Button>
                            ) : null}
                            {canEdit ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="tipo-equipo-edit-button"
                                    onClick={() => {
                                        setEditingTipoEquipoId(row.original.id);
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
                                    data-test="tipo-equipo-delete-button"
                                    onClick={() => {
                                        setDeletingTipoEquipo(row.original);
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

                <DataTable
                    data={tiposEquipo}
                    columns={columns}
                    searchPlaceholder="Buscar tipo de equipo..."
                    emptyMessage="Aún no has registrado tipos de equipo."
                    rowTestId="tipo-equipo-row"
                />
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
