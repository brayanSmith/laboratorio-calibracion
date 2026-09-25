import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PermissionCatalogGroup } from '@/types';

type Props = PropsWithChildren<{
    catalog: PermissionCatalogGroup[];
}>;

export default function CreateRoleModal({ catalog, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={String(open)}
                    {...RoleController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo rol</DialogTitle>
                                <DialogDescription>
                                    Define qué puede hacer quien tenga este rol
                                </DialogDescription>
                            </DialogHeader>

                            <RoleFormFields catalog={catalog} errors={errors} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="role-create-submit"
                                >
                                    Guardar rol
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
