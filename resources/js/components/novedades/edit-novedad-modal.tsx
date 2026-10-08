import { Form } from '@inertiajs/react';
import NovedadController from '@/actions/App/Http/Controllers/NovedadController';
import NovedadFormFields from '@/components/novedades/novedad-form-fields';
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
import type { Novedad } from '@/types';

type Props = {
    novedad: Novedad | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditNovedadModal({
    novedad,
    open,
    onOpenChange,
}: Props) {
    if (!novedad) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${novedad.id}-${String(open)}`}
                    {...NovedadController.update.form(novedad.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar novedad</DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre o la categoría de la
                                    novedad
                                </DialogDescription>
                            </DialogHeader>

                            <NovedadFormFields
                                novedad={novedad}
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
                                    data-test="novedad-update-submit"
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
