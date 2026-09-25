import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import CreateRoleModal from '@/components/roles/create-role-modal';
import DeleteRoleModal from '@/components/roles/delete-role-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/roles';
import type { PermissionCatalogGroup, RoleSummary } from '@/types';

type Props = {
    roles: RoleSummary[];
    catalog: PermissionCatalogGroup[];
};

export default function RolesIndex({ roles, catalog }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [roleDeleting, setRoleDeleting] = useState<RoleSummary | null>(null);

    const totalPermissions = catalog.reduce(
        (total, group) => total + group.permissions.length,
        0,
    );

    return (
        <>
            <Head title="Roles" />

            <h1 className="sr-only">Roles</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Roles"
                        description="Define qué puede hacer cada persona de tu laboratorio"
                    />

                    <CreateRoleModal catalog={catalog}>
                        <Button data-test="roles-new-button">
                            <Plus /> Nuevo rol
                        </Button>
                    </CreateRoleModal>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Rol</th>
                                <th className="px-4 py-3 font-medium">
                                    Permisos
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Usuarios
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {roles.map((role) => (
                                <tr key={role.id} data-test="role-row">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {role.name}
                                            {role.is_system ? (
                                                <Badge variant="secondary">
                                                    Sistema
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {role.permissions.length} de{' '}
                                        {totalPermissions}
                                    </td>
                                    <td className="px-4 py-3">
                                        {role.users_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {role.is_system ? null : (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(role.id)}
                                                            data-test="role-edit-button"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="role-delete-button"
                                                        onClick={() => {
                                                            setRoleDeleting(
                                                                role,
                                                            );
                                                            setDeleteDialogOpen(
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-xs text-muted-foreground">
                    El rol Administrador siempre tiene todos los permisos y no
                    se puede editar ni eliminar.
                </p>
            </div>

            <DeleteRoleModal
                role={roleDeleting}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: index(),
        },
    ],
};
