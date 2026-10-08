import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CreateAlcanceMedicionModal from '@/components/alcances-medicion/create-alcance-medicion-modal';
import DeleteAlcanceMedicionModal from '@/components/alcances-medicion/delete-alcance-medicion-modal';
import EditAlcanceMedicionModal from '@/components/alcances-medicion/edit-alcance-medicion-modal';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/alcances-medicion';
import type {
    AlcanceMedicion,
    AlcanceMedicionOpcion,
    AlcanceMedicionUnidadOpcion,
} from '@/types';

type Props = {
    alcancesMedicion: AlcanceMedicion[];
    tiposEquipo: AlcanceMedicionOpcion[];
    alcancesIndicacion: Record<string, string[]>;
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
};

const columnHelper = createDataTableColumnHelper<AlcanceMedicion>();

export default function AlcancesMedicionIndex({
    alcancesMedicion,
    tiposEquipo,
    alcancesIndicacion,
    unidadesMedida,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('alcances-medicion.editar');
    const canDelete = can('alcances-medicion.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        alcancesMedicion.find((alcance) => alcance.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<AlcanceMedicion | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('tipo_equipo', {
                    header: 'Tipo de equipo',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('alcance_indicacion', {
                    header: 'Alcance de indicación',
                }),
                columnHelper.accessor((alcance) => alcance.detalles.length, {
                    id: 'detalles',
                    header: 'Detalles',
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
                                    data-test="alcance-medicion-edit-button"
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
                                    data-test="alcance-medicion-delete-button"
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
            <Head title="Alcances de medición" />

            <h1 className="sr-only">Alcances de medición</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Alcances de medición"
                        description="Define los alcances de cada tipo de equipo y sus valores de EMP e incertidumbre"
                    />

                    {can('alcances-medicion.crear') ? (
                        <CreateAlcanceMedicionModal
                            tiposEquipo={tiposEquipo}
                            alcancesIndicacion={alcancesIndicacion}
                            unidadesMedida={unidadesMedida}
                        >
                            <Button data-test="alcances-medicion-new-button">
                                <Plus /> Nuevo alcance
                            </Button>
                        </CreateAlcanceMedicionModal>
                    ) : null}
                </div>

                <DataTable
                    data={alcancesMedicion}
                    columns={columns}
                    searchPlaceholder="Buscar alcance..."
                    emptyMessage="Aún no has registrado alcances de medición."
                    rowTestId="alcance-medicion-row"
                />
            </div>

            <EditAlcanceMedicionModal
                alcanceMedicion={editing}
                tiposEquipo={tiposEquipo}
                alcancesIndicacion={alcancesIndicacion}
                unidadesMedida={unidadesMedida}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteAlcanceMedicionModal
                alcanceMedicion={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

AlcancesMedicionIndex.layout = {
    breadcrumbs: [
        {
            title: 'Alcances de medición',
            href: index(),
        },
    ],
};
