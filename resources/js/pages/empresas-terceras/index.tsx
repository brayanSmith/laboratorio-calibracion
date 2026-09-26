import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

export default function EmpresasTercerasIndex({ empresasTerceras }: Props) {
    const { can } = usePermissions();
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

    return (
        <>
            <Head title="Empresas terceras" />

            <h1 className="sr-only">Empresas terceras</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Empresas terceras"
                        description="Administra las empresas externas que prestan servicios de mantenimiento o calibración"
                    />

                    {can('empresas-terceras.crear') ? (
                        <CreateEmpresaTerceroModal>
                            <Button data-test="empresas-terceras-new-button">
                                <Plus /> Nueva empresa tercera
                            </Button>
                        </CreateEmpresaTerceroModal>
                    ) : null}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nombre
                                </th>
                                <th className="px-4 py-3 font-medium">NIT</th>
                                <th className="px-4 py-3 font-medium">
                                    Teléfono
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Correo
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Servicios
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {empresasTerceras.map((empresaTercero) => (
                                <tr
                                    key={empresaTercero.id}
                                    data-test="empresa-tercero-row"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {empresaTercero.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        {empresaTercero.nit}
                                    </td>
                                    <td className="px-4 py-3">
                                        {empresaTercero.telefono ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {empresaTercero.email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {empresaTercero.servicios_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('empresas-terceras.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="empresa-tercero-edit-button"
                                                    onClick={() => {
                                                        setEditingEmpresaTerceroId(
                                                            empresaTercero.id,
                                                        );
                                                        setEditOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {can(
                                                'empresas-terceras.eliminar',
                                            ) ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="empresa-tercero-delete-button"
                                                    onClick={() => {
                                                        setDeletingEmpresaTercero(
                                                            empresaTercero,
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

                            {empresasTerceras.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado empresas terceras.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
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
            title: 'Empresas terceras',
            href: index(),
        },
    ],
};
