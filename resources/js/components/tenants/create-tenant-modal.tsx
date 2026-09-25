import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Plataforma/TenantController';
import TenantFormFields from '@/components/tenants/tenant-form-fields';
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

export default function CreateTenantModal({ children }: PropsWithChildren) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <Form
                    key={String(open)}
                    {...TenantController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo tenant</DialogTitle>
                                <DialogDescription>
                                    Registra un nuevo laboratorio y a su
                                    administrador
                                </DialogDescription>
                            </DialogHeader>

                            <TenantFormFields errors={errors} includeAdmin />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="tenant-create-submit"
                                >
                                    Guardar tenant
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
