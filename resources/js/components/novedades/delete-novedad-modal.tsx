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
import { destroy } from '@/routes/novedades';
import type { Novedad } from '@/types';

type Props = {
    novedad: Novedad | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteNovedadModal({
    novedad,
    open,
    onOpenChange,
}: Props) {
    if (!novedad) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(novedad.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar novedad?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará la novedad{' '}
                                    <strong>"{novedad.nombre}"</strong>. Solo es
                                    posible si no se ha usado en calibraciones,
                                    mantenimientos, salidas o programaciones.
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
                                    data-test="novedad-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar novedad
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
