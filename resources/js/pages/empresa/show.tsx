import { Head } from '@inertiajs/react';
import { Building, Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CreateEmpresaModal from '@/components/empresa/create-empresa-modal';
import DeleteEmpresaModal from '@/components/empresa/delete-empresa-modal';
import EditEmpresaModal from '@/components/empresa/edit-empresa-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { show } from '@/routes/empresa';
import type { Empresa } from '@/types';

type Props = {
    empresa: Empresa | null;
};

function Field({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-1">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">{value}</span>
        </div>
    );
}

export default function EmpresaShow({ empresa }: Props) {
    const { can } = usePermissions();
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title="Mi empresa" />

            <h1 className="sr-only">Mi empresa</h1>

            <div className="max-w-3xl space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Mi empresa"
                        description="Datos de la organización de tu laboratorio"
                    />

                    {empresa ? (
                        <div className="flex items-center gap-2">
                            {can('empresa.editar') ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditOpen(true)}
                                    data-test="empresa-edit-button"
                                >
                                    <Pencil /> Editar
                                </Button>
                            ) : null}
                            {can('empresa.eliminar') ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setDeleteOpen(true)}
                                    data-test="empresa-delete-button"
                                >
                                    <Trash2 className="h-4 w-4" /> Eliminar
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {empresa ? (
                    <div className="grid gap-6 rounded-lg border p-6 sm:grid-cols-[auto_1fr]">
                        {empresa.logo_url ? (
                            <img
                                src={empresa.logo_url}
                                alt={`Logo de ${empresa.nombre}`}
                                className="h-24 w-24 rounded-md border bg-white object-contain p-1"
                            />
                        ) : (
                            <div className="flex h-24 w-24 items-center justify-center rounded-md border border-dashed text-muted-foreground">
                                <Building className="h-8 w-8" />
                            </div>
                        )}

                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field label="Nombre" value={empresa.nombre} />
                            <Field label="NIT" value={empresa.nit} />
                            <Field
                                label="Dirección"
                                value={empresa.direccion}
                            />
                            <Field label="Teléfono" value={empresa.telefono} />
                        </div>
                    </div>
                ) : (
                    <div
                        className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-center"
                        data-test="empresa-empty"
                    >
                        <Building className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tu laboratorio aún no ha registrado su empresa.
                        </p>
                        {can('empresa.crear') ? (
                            <CreateEmpresaModal>
                                <Button data-test="empresa-new-button">
                                    <Plus /> Registrar empresa
                                </Button>
                            </CreateEmpresaModal>
                        ) : null}
                    </div>
                )}
            </div>

            {empresa ? (
                <>
                    <EditEmpresaModal
                        empresa={empresa}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                    />
                    <DeleteEmpresaModal
                        empresa={empresa}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                    />
                </>
            ) : null}
        </>
    );
}

EmpresaShow.layout = {
    breadcrumbs: [
        {
            title: 'Mi empresa',
            href: show(),
        },
    ],
};
