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
import { destroy } from '@/routes/unidades-medida';
import type { UnidadMedida } from '@/types';

type Props = {
    unidadMedida: UnidadMedida | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteUnidadMedidaModal({
    unidadMedida,
    open,
    onOpenChange,
}: Props) {
    if (!unidadMedida) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(unidadMedida.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar unidad de medida?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará la unidad{' '}
                                    <strong>"{unidadMedida.nombre}"</strong>.
                                    Solo es posible si no está en uso.
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
                                    data-test="unidad-medida-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar unidad de medida
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
