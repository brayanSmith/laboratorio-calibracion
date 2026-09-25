import { Form } from '@inertiajs/react';
import TenantUserController from '@/actions/App/Http/Controllers/Plataforma/TenantUserController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Tenant, TenantUser } from '@/types';

type Props = {
    tenant: Tenant;
    user: TenantUser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditTenantUserModal({
    tenant,
    user,
    open,
    onOpenChange,
}: Props) {
    if (!user) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${user.id}-${String(open)}`}
                    {...TenantUserController.update.form([tenant.id, user.id])}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar usuario</DialogTitle>
                                <DialogDescription>
                                    Si el correo estaba mal escrito, corrígelo y
                                    después regenera la contraseña temporal para
                                    que solo la conozca la persona correcta.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="user_name">Nombre</Label>
                                    <Input
                                        id="user_name"
                                        name="name"
                                        defaultValue={user.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="user_email">
                                        Correo electrónico
                                    </Label>
                                    <Input
                                        id="user_email"
                                        name="email"
                                        type="email"
                                        defaultValue={user.email}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
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
                                    data-test="tenant-user-update-submit"
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
