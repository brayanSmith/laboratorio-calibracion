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
import { destroy } from '@/routes/calibraciones';
import type { Calibracion } from '@/types';

type Props = {
    calibracion: Calibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteCalibracionModal({
    calibracion,
    open,
    onOpenChange,
}: Props) {
    if (!calibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(calibracion.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar calibración?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará la calibración de{' '}
                                    <strong>
                                        "{calibracion.equipo.codigo}"
                                    </strong>{' '}
                                    de la orden de trabajo{' '}
                                    {calibracion.orden_trabajo_codigo}.
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
                                    data-test="calibracion-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar calibración
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
