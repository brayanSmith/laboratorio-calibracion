import { Form } from '@inertiajs/react';
import TipoMagnitudController from '@/actions/App/Http/Controllers/TipoMagnitudController';
import TipoMagnitudFormFields from '@/components/tipos-magnitud/tipo-magnitud-form-fields';
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
import type { TipoMagnitud } from '@/types';

type Props = {
    tipoMagnitud: TipoMagnitud | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditTipoMagnitudModal({
    tipoMagnitud,
    open,
    onOpenChange,
}: Props) {
    if (!tipoMagnitud) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${tipoMagnitud.id}-${String(open)}`}
                    {...TipoMagnitudController.update.form(tipoMagnitud.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Editar tipo de magnitud
                                </DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre del tipo de magnitud
                                </DialogDescription>
                            </DialogHeader>

                            <TipoMagnitudFormFields
                                tipoMagnitud={tipoMagnitud}
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
                                    data-test="tipo-magnitud-update-submit"
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
