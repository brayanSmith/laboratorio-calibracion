import { Form } from '@inertiajs/react';
import CalibracionController from '@/actions/App/Http/Controllers/CalibracionController';
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
import type { Calibracion } from '@/types';

type Props = {
    calibracion: Calibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function IniciarCalibracionModal({
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
                    {...CalibracionController.iniciar.form(calibracion.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Iniciar calibración?</DialogTitle>
                                <DialogDescription>
                                    La calibración se iniciará y se empezará a
                                    medir el tiempo. ¿Desea continuar?
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="iniciar-calibracion-confirm"
                                >
                                    Sí, iniciar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
