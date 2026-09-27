import { Form } from '@inertiajs/react';
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
} from '@/components/ui/dialog';
import type { Bahia, BahiaAreaOption } from '@/types';

type Props = {
    bahia: Bahia | null;
    areas: BahiaAreaOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditBahiaModal({
    bahia,
    areas,
    open,
    onOpenChange,
}: Props) {
    if (!bahia) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${bahia.id}-${String(open)}`}
                    {...BahiaController.update.form(bahia.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar bahía</DialogTitle>
                                <DialogDescription>
                                    Actualiza el área o el nombre de la bahía
                                </DialogDescription>
                            </DialogHeader>

                            <BahiaFormFields
                                bahia={bahia}
                                areas={areas}
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
                                    data-test="bahia-update-submit"
                                >
                                    Guardar cambios
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
