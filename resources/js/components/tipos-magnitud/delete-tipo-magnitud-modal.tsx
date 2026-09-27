import { Form } from '@inertiajs/react';
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
import { destroy } from '@/routes/tipos-magnitud';
import type { TipoMagnitud } from '@/types';

type Props = {
    tipoMagnitud: TipoMagnitud | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteTipoMagnitudModal({
    tipoMagnitud,
    open,
    onOpenChange,
}: Props) {
    if (!tipoMagnitud) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(tipoMagnitud.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar tipo de magnitud?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el tipo de magnitud{' '}
                                    <strong>"{tipoMagnitud.nombre}"</strong>.
                                    Solo es posible si no tiene especificaciones
                                    técnicas asociadas.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="tipo-magnitud-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar tipo de magnitud
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
