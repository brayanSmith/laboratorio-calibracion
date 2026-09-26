import { Form } from '@inertiajs/react';
import FabricanteController from '@/actions/App/Http/Controllers/FabricanteController';
import FabricanteFormFields from '@/components/fabricantes/fabricante-form-fields';
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
import type { Fabricante } from '@/types';

type Props = {
    fabricante: Fabricante | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditFabricanteModal({
    fabricante,
    open,
    onOpenChange,
}: Props) {
    if (!fabricante) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${fabricante.id}-${String(open)}`}
                    {...FabricanteController.update.form(fabricante.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar fabricante</DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre del fabricante
                                </DialogDescription>
                            </DialogHeader>

                            <FabricanteFormFields
                                fabricante={fabricante}
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
                                    data-test="fabricante-update-submit"
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
