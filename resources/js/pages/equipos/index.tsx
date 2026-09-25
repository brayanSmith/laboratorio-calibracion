import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateEquipoModal from '@/components/equipos/create-equipo-modal';
import DeleteEquipoModal from '@/components/equipos/delete-equipo-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { edit, index } from '@/routes/equipos';
import type { Equipo, EquipoFormOptions, EquipoPaginator } from '@/types';

type Props = {
    equipos: EquipoPaginator;
    options: EquipoFormOptions;
};

export default function EquiposIndex({ equipos, options }: Props) {
    const { can } = usePermissions();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [equipoDeleting, setEquipoDeleting] = useState<Equipo | null>(null);

    const openDeleteDialog = (equipo: Equipo) => {
        setEquipoDeleting(equipo);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Equipos" />

            <h1 className="sr-only">Equipos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Equipos"
                        description="Gestiona los equipos de metrología del laboratorio"
                    />

                    {can('equipos.crear') ? (
                        <CreateEquipoModal options={options}>
                            <Button data-test="equipos-new-button">
                                <Plus /> Nuevo equipo
                            </Button>
                        </CreateEquipoModal>
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
                                    Modelo
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tipo de equipo
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Fabricante
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Cliente
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Estado
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {equipos.data.map((equipo) => (
                                <tr key={equipo.id} data-test="equipo-row">
                                    <td className="px-4 py-3 font-medium">
                                        {equipo.codigo}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.modelo}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.tipo_equipo?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.fabricante?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.cliente?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                equipo.activo
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {equipo.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('equipos.editar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(equipo.id)}
                                                        data-test="equipo-edit-button"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            ) : null}
                                            {can('equipos.eliminar') ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="equipo-delete-button"
                                                    onClick={() =>
                                                        openDeleteDialog(equipo)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {equipos.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aún no has registrado equipos.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                {equipos.last_page > 1 ? (
                    <div className="flex flex-wrap items-center gap-1">
                        {equipos.links.map((link, index) =>
                            link.url ? (
                                <Button
                                    key={index}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ) : (
                                <Button
                                    key={index}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>

            <DeleteEquipoModal
                equipo={equipoDeleting}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

EquiposIndex.layout = {
    breadcrumbs: [
        {
            title: 'Equipos',
            href: index(),
        },
    ],
};
