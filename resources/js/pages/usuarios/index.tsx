import { Head } from '@inertiajs/react';
import { KeyRound, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import Heading from '@/components/heading';
import ResetUserPasswordModal from '@/components/reset-user-password-modal';
import CreateUsuarioModal from '@/components/usuarios/create-usuario-modal';
import EditUsuarioModal from '@/components/usuarios/edit-usuario-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/usuarios';
import type { TenantRoleOption, Usuario } from '@/types';

type Props = {
    usuarios: Usuario[];
    roles: TenantRoleOption[];
};

export default function UsuariosIndex({ usuarios, roles }: Props) {
    const [editingUsuario, setEditingUsuario] = useState<Usuario | null>(null);
    const [editOpen, setEditOpen] = useState(false);
    const [resettingUsuario, setResettingUsuario] = useState<Usuario | null>(
        null,
    );
    const [resetOpen, setResetOpen] = useState(false);

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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nombre
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Correo
                                </th>
                                <th className="px-4 py-3 font-medium">Rol</th>
                                <th className="px-4 py-3 font-medium">
                                    Acceso
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {usuarios.map((usuario) => (
                                <tr key={usuario.id} data-test="usuario-row">
                                    <td className="px-4 py-3 font-medium">
                                        {usuario.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {usuario.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {usuario.role_name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {usuario.must_change_password ? (
                                            <Badge variant="secondary">
                                                Pendiente de cambio de
                                                contraseña
                                            </Badge>
                                        ) : (
                                            <Badge>Activo</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {usuario.can_manage ? (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditingUsuario(
                                                                usuario,
                                                            );
                                                            setEditOpen(true);
                                                        }}
                                                        data-test="usuario-edit-button"
                                                    >
                                                        <Pencil className="h-4 w-4" />{' '}
                                                        Editar
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setResettingUsuario(
                                                                usuario,
                                                            );
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
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
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
