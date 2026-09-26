import { Head } from '@inertiajs/react';
import { KeyRound, Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import ResetUserPasswordModal from '@/components/reset-user-password-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CreateUsuarioModal from '@/components/usuarios/create-usuario-modal';
import EditUsuarioModal from '@/components/usuarios/edit-usuario-modal';
import { index } from '@/routes/usuarios';
import type { TenantRoleOption, Usuario } from '@/types';

type Props = {
    usuarios: Usuario[];
    roles: TenantRoleOption[];
};

const columnHelper = createDataTableColumnHelper<Usuario>();

export default function UsuariosIndex({ usuarios, roles }: Props) {
    const [editingUsuario, setEditingUsuario] = useState<Usuario | null>(null);
    const [editOpen, setEditOpen] = useState(false);
    const [resettingUsuario, setResettingUsuario] = useState<Usuario | null>(
        null,
    );
    const [resetOpen, setResetOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('name', {
                    header: 'Nombre',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('email', { header: 'Correo' }),
                columnHelper.accessor('role_name', {
                    header: 'Rol',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('must_change_password', {
                    header: 'Acceso',
                    cell: (info) =>
                        info.getValue() ? (
                            <Badge variant="secondary">
                                Pendiente de cambio de contraseña
                            </Badge>
                        ) : (
                            <Badge>Activo</Badge>
                        ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {row.original.can_manage ? (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setEditingUsuario(row.original);
                                            setEditOpen(true);
                                        }}
                                        data-test="usuario-edit-button"
                                    >
                                        <Pencil className="h-4 w-4" /> Editar
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setResettingUsuario(row.original);
                                            setResetOpen(true);
                                        }}
                                        data-test="usuario-reset-button"
                                    >
                                        <KeyRound className="h-4 w-4" />{' '}
                                        Regenerar contraseña
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [],
    );

    return (
        <>
            <Head title="Usuarios" />

            <h1 className="sr-only">Usuarios</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Usuarios"
                        description="Gestiona las cuentas de tu laboratorio y el rol de cada una"
                    />

                    <CreateUsuarioModal roles={roles}>
                        <Button data-test="usuarios-new-button">
                            <Plus /> Nuevo usuario
                        </Button>
                    </CreateUsuarioModal>
                </div>

                <DataTable
                    data={usuarios}
                    columns={columns}
                    searchPlaceholder="Buscar usuario..."
                    emptyMessage="Aún no hay usuarios registrados."
                    rowTestId="usuario-row"
                />
            </div>

            <EditUsuarioModal
                usuario={editingUsuario}
                roles={roles}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <ResetUserPasswordModal
                formFor={(userId) =>
                    UsuarioController.resetPassword.form(userId)
                }
                user={resettingUsuario}
                open={resetOpen}
                onOpenChange={setResetOpen}
            />
        </>
    );
}

UsuariosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: index(),
        },
    ],
};
