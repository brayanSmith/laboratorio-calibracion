import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
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

const columnHelper = createDataTableColumnHelper<RoleSummary>();

export default function RolesIndex({ roles, catalog }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [roleDeleting, setRoleDeleting] = useState<RoleSummary | null>(null);

    const totalPermissions = catalog.reduce(
        (total, group) => total + group.permissions.length,
        0,
    );

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('name', {
                    header: 'Rol',
                    cell: ({ row }) => (
                        <div className="flex items-center gap-2 font-medium">
                            {row.original.name}
                            {row.original.is_system ? (
                                <Badge variant="secondary">Sistema</Badge>
                            ) : null}
                        </div>
                    ),
                }),
                columnHelper.accessor((role) => role.permissions.length, {
                    id: 'permisos',
                    header: 'Permisos',
                    cell: (info) => `${info.getValue()} de ${totalPermissions}`,
                }),
                columnHelper.accessor('users_count', { header: 'Usuarios' }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {row.original.is_system ? null : (
                                <>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={edit(row.original.id)}
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
                                            setRoleDeleting(row.original);
                                            setDeleteDialogOpen(true);
                                        }}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </>
                            )}
                        </div>
                    ),
                }),
            ]),
        [totalPermissions],
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

                <DataTable
                    data={roles}
                    columns={columns}
                    searchPlaceholder="Buscar rol..."
                    emptyMessage="Aún no hay roles registrados."
                    rowTestId="role-row"
                />

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
