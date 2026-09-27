import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import BahiaController from '@/actions/App/Http/Controllers/BahiaController';
import BahiaFormFields from '@/components/bahias/bahia-form-fields';
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
import type { BahiaAreaOption } from '@/types';

type Props = PropsWithChildren<{
    areas: BahiaAreaOption[];
}>;

export default function CreateBahiaModal({ areas, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={String(open)}
                    {...BahiaController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nueva bahía</DialogTitle>
                                <DialogDescription>
                                    Registra una bahía dentro de un área
                                </DialogDescription>
                            </DialogHeader>

                            <BahiaFormFields areas={areas} errors={errors} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="bahia-create-submit"
                                >
                                    Guardar bahía
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
