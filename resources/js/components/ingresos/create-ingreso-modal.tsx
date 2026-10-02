import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import IngresoController from '@/actions/App/Http/Controllers/IngresoController';
import IngresoFormFields from '@/components/ingresos/ingreso-form-fields';
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
import type { IngresoOptions } from '@/types';

type Props = PropsWithChildren<{
    options: IngresoOptions;
}>;

export default function CreateIngresoModal({ options, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={String(open)}
                    {...IngresoController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo ingreso</DialogTitle>
                                <DialogDescription>
                                    Registra la recepción de equipos de un
                                    cliente en una bahía
                                </DialogDescription>
                            </DialogHeader>

                            <IngresoFormFields
                                options={options}
                                errors={errors}
                                mostrarBuscador
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
                                    data-test="ingreso-create-submit"
                                >
                                    Guardar ingreso
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
