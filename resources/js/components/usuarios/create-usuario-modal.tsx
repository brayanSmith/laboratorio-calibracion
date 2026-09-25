import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
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
import RoleSelect from '@/components/usuarios/role-select';
import type { TenantRoleOption } from '@/types';

type Props = PropsWithChildren<{
    roles: TenantRoleOption[];
}>;

export default function CreateUsuarioModal({ roles, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <Form
                    key={String(open)}
                    {...UsuarioController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo usuario</DialogTitle>
                                <DialogDescription>
                                    Crea una cuenta para alguien de tu
                                    laboratorio y asígnale un rol
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="new_user_name">
                                        Nombre
                                    </Label>
                                    <Input
                                        id="new_user_name"
                                        name="name"
                                        autoComplete="off"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new_user_email">
                                        Correo electrónico
                                    </Label>
                                    <Input
                                        id="new_user_email"
                                        name="email"
                                        type="email"
                                        autoComplete="off"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <RoleSelect
                                    roles={roles}
                                    error={errors.role_id}
                                />

                                <TemporaryPasswordField
                                    id="new_user_password"
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
                                    data-test="usuario-create-submit"
                                >
                                    Crear usuario
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
