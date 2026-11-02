import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import BotonAccion from '@/components/boton-accion';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import CreateEmpresaTerceroModal from '@/components/empresas-terceras/create-empresa-tercero-modal';
import DeleteEmpresaTerceroModal from '@/components/empresas-terceras/delete-empresa-tercero-modal';
import EditEmpresaTerceroModal from '@/components/empresas-terceras/edit-empresa-tercero-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/empresas-terceras';
import type { EmpresaTercero } from '@/types';

type Props = {
    empresasTerceras: EmpresaTercero[];
};

const columnHelper = createDataTableColumnHelper<EmpresaTercero>();

export default function EmpresasTercerasIndex({ empresasTerceras }: Props) {
    const { can } = usePermissions();
    const canEdit = can('empresas-terceras.editar');
    const canDelete = can('empresas-terceras.eliminar');
    const [editingEmpresaTerceroId, setEditingEmpresaTerceroId] = useState<
        number | null
    >(null);
    const editingEmpresaTercero =
        empresasTerceras.find(
            (empresaTercero) => empresaTercero.id === editingEmpresaTerceroId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deletingEmpresaTercero, setDeletingEmpresaTercero] =
        useState<EmpresaTercero | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('nombre', {
                    header: 'Razon Social',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('nit', { header: 'RUC' }),
                columnHelper.accessor('telefono', {
                    header: 'Teléfono',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('email', {
                    header: 'Correo',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('servicios_count', {
                    header: 'Servicios',
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {canEdit ? (
                                <BotonAccion
                                    icono={Pencil}
                                    etiqueta="Editar"
                                    data-test="empresa-tercero-edit-button"
                                    onClick={() => {
                                        setEditingEmpresaTerceroId(
                                            row.original.id,
                                        );
                                        setEditOpen(true);
                                    }}
                                />
                            ) : null}
                            {canDelete ? (
                                <BotonAccion
                                    icono={Trash2}
                                    etiqueta="Eliminar"
                                    data-test="empresa-tercero-delete-button"
                                    onClick={() => {
                                        setDeletingEmpresaTercero(row.original);
                                        setDeleteOpen(true);
                                    }}
                                />
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [canEdit, canDelete],
    );

    return (
        <>
            <Head title="Laboratorios terceros" />

            <h1 className="sr-only">Laboratorios terceros</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Laboratorios terceros"
                        description="Administra los laboratorios externos que prestan servicios de mantenimiento o calibración"
                    />

                    {can('empresas-terceras.crear') ? (
                        <CreateEmpresaTerceroModal>
                            <Button data-test="empresas-terceras-new-button">
                                <Plus /> Nuevo laboratorio tercero
                            </Button>
                        </CreateEmpresaTerceroModal>
                    ) : null}
                </div>

                <DataTable
                    data={empresasTerceras}
                    columns={columns}
                    searchPlaceholder="Buscar laboratorio..."
                    emptyMessage="Aún no has registrado laboratorios terceros."
                    rowTestId="empresa-tercero-row"
                />
            </div>

            <EditEmpresaTerceroModal
                empresaTercero={editingEmpresaTercero}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteEmpresaTerceroModal
                empresaTercero={deletingEmpresaTercero}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

EmpresasTercerasIndex.layout = {
    breadcrumbs: [
        {
            title: 'Laboratorios terceros',
            href: index(),
        },
    ],
};
