import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import EmpresaTerceroController from '@/actions/App/Http/Controllers/EmpresaTerceroController';
import EmpresaTerceroFormFields from '@/components/empresas-terceras/empresa-tercero-form-fields';
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

export default function CreateEmpresaTerceroModal({
    children,
}: PropsWithChildren) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={String(open)}
                    {...EmpresaTerceroController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nueva empresa tercera</DialogTitle>
                                <DialogDescription>
                                    Registra una empresa que presta servicios
                                    externos
                                </DialogDescription>
                            </DialogHeader>

                            <EmpresaTerceroFormFields errors={errors} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="empresaTercero-create-submit"
                                >
                                    Guardar empresa tercera
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
