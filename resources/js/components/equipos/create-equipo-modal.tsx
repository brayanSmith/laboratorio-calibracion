import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import EquipoController from '@/actions/App/Http/Controllers/EquipoController';
import EquipoFormFields from '@/components/equipos/equipo-form-fields';
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
import type { EquipoFormOptions } from '@/types';

type Props = PropsWithChildren<{
    options: EquipoFormOptions;
}>;

export default function CreateEquipoModal({ options, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={String(open)}
                    {...EquipoController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo equipo</DialogTitle>
                                <DialogDescription>
                                    Registra un nuevo equipo de metrología
                                </DialogDescription>
                            </DialogHeader>

                            <EquipoFormFields
                                options={options}
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
                                    data-test="equipo-create-submit"
                                >
                                    Guardar equipo
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
