import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import TenantUserController from '@/actions/App/Http/Controllers/Plataforma/TenantUserController';
import InputError from '@/components/input-error';
import TemporaryPasswordField from '@/components/tenants/temporary-password-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Tenant } from '@/types';

type Props = PropsWithChildren<{
    tenant: Tenant;
    roles: string[];
}>;

export default function CreateTenantUserModal({
    tenant,
    roles,
    children,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <Form
                    key={String(open)}
                    {...TenantUserController.store.form(tenant.id)}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo usuario</DialogTitle>
                                <DialogDescription>
                                    Crea una cuenta para alguien de{' '}
                                    {tenant.nombre} y asígnale un rol
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="new_tenant_user_name">
                                        Nombre
                                    </Label>
                                    <Input
                                        id="new_tenant_user_name"
                                        name="name"
                                        autoComplete="off"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new_tenant_user_email">
                                        Correo electrónico
                                    </Label>
                                    <Input
                                        id="new_tenant_user_email"
                                        name="email"
                                        type="email"
                                        autoComplete="off"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new_tenant_user_role">
                                        Rol
                                    </Label>
                                    <Select name="role" defaultValue={roles[0]}>
                                        <SelectTrigger
                                            id="new_tenant_user_role"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Selecciona un rol" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role}
                                                    value={role}
                                                >
                                                    {role}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                <TemporaryPasswordField
                                    id="new_tenant_user_password"
                                    name="password"
                                    error={errors.password}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="tenant-user-create-submit"
                                >
                                    Guardar usuario
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
