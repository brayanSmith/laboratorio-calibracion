import { Form } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import RoleFormFields from '@/components/roles/role-form-fields';
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
import type { PermissionCatalogGroup, RoleSummary } from '@/types';

type Props = {
    role: RoleSummary | null;
    catalog: PermissionCatalogGroup[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditRoleModal({
    role,
    catalog,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
                {role ? (
                    <Form
                        key={role.id}
                        {...RoleController.update.form(role.id)}
                        className="space-y-6"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Editar rol {role.name}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Actualiza el nombre y los permisos del
                                        rol
                                    </DialogDescription>
                                </DialogHeader>

                                <RoleFormFields
                                    role={role}
                                    catalog={catalog}
                                    errors={errors}
                                />

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Cancelar
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="role-update-button"
                                    >
                                        Guardar cambios
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
