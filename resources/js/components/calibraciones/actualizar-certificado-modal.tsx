import { Form } from '@inertiajs/react';
import CalibracionController from '@/actions/App/Modules/Calibraciones/Http/Controllers/CalibracionController';
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

export default function ActualizarCertificadoModal({
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
                    {...CalibracionController.actualizarCertificado.form(
                        calibracion.id,
                    )}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Actualizar certificado?
                                </DialogTitle>
                                <DialogDescription>
                                    El certificado PDF se volverá a generar con
                                    los datos y el diseño actuales y reemplazará
                                    al guardado. ¿Desea continuar?
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
                                    loading={processing}
                                    data-test="actualizar-certificado-confirm"
                                >
                                    Sí, actualizar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
