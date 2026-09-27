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
import { destroy } from '@/routes/procedimientos-calibracion';
import type { ProcedimientoCalibracion } from '@/types';

type Props = {
    procedimientoCalibracion: ProcedimientoCalibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteProcedimientoCalibracionModal({
    procedimientoCalibracion,
    open,
    onOpenChange,
}: Props) {
    if (!procedimientoCalibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(procedimientoCalibracion.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar procedimiento de calibración?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el procedimiento{' '}
                                    <strong>
                                        "{procedimientoCalibracion.nombre}"
                                    </strong>
                                    . Solo es posible si no tiene calibraciones
                                    asociadas.
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
                                    data-test="procedimiento-calibracion-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar procedimiento
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
