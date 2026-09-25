import { KeyRound, Pencil } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import EditTenantUserModal from '@/components/tenants/edit-tenant-user-modal';
import ResetTenantUserPasswordModal from '@/components/tenants/reset-tenant-user-password-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Tenant, TenantUser } from '@/types';

type Props = {
    tenant: Tenant;
    users: TenantUser[];
};

export default function TenantUsers({ tenant, users }: Props) {
    const [editingUser, setEditingUser] = useState<TenantUser | null>(null);
    const [editOpen, setEditOpen] = useState(false);
    const [resettingUser, setResettingUser] = useState<TenantUser | null>(
        null,
    );
    const [resetOpen, setResetOpen] = useState(false);

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title="Usuarios del tenant"
                description="Corrige el correo de un usuario o regenera su contraseña temporal"
            />

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Correo</th>
                            <th className="px-4 py-3 font-medium">Acceso</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {users.map((user) => (
                            <tr key={user.id} data-test="tenant-user-row">
                                <td className="px-4 py-3 font-medium">
                                    {user.name}
                                </td>
                                <td className="px-4 py-3">{user.email}</td>
                                <td className="px-4 py-3">
                                    {user.must_change_password ? (
                                        <Badge variant="secondary">
                                            Pendiente de cambio de contraseña
                                        </Badge>
                                    ) : (
                                        <Badge>Activo</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setEditingUser(user);
                                                setEditOpen(true);
                                            }}
                                            data-test="tenant-user-edit-button"
                                        >
                                            <Pencil className="h-4 w-4" /> Editar
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setResettingUser(user);
                                                setResetOpen(true);
                                            }}
                                            data-test="tenant-user-reset-button"
                                        >
                                            <KeyRound className="h-4 w-4" />{' '}
                                            Regenerar contraseña
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}

                        {users.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Este tenant aún no tiene usuarios.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>

            <EditTenantUserModal
                tenant={tenant}
                user={editingUser}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <ResetTenantUserPasswordModal
                tenant={tenant}
                user={resettingUser}
                open={resetOpen}
                onOpenChange={setResetOpen}
            />
        </div>
    );
}
