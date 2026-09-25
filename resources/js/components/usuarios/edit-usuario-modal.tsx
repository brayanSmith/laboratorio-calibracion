import { Form } from '@inertiajs/react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
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
import RoleSelect from '@/components/usuarios/role-select';
import type { TenantRoleOption, Usuario } from '@/types';

type Props = {
    usuario: Usuario | null;
    roles: TenantRoleOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditUsuarioModal({
    usuario,
    roles,
    open,
    onOpenChange,
}: Props) {
    if (!usuario) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${usuario.id}-${String(open)}`}
                    {...UsuarioController.update.form(usuario.id)}
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
                                        defaultValue={usuario.name}
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
                                        defaultValue={usuario.email}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <RoleSelect
                                    roles={roles}
                                    defaultValue={usuario.role_id}
                                    error={errors.role_id}
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
                                    data-test="usuario-update-submit"
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
