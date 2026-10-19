import { Form } from '@inertiajs/react';
import MantenimientoController from '@/actions/App/Http/Controllers/MantenimientoController';
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
import type { Mantenimiento } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ActualizarInformeModal({
    mantenimiento,
    open,
    onOpenChange,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...MantenimientoController.actualizarInforme.form(mantenimiento.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Actualizar informe?</DialogTitle>
                                <DialogDescription>
                                    El informe PDF se volverá a generar con los
                                    datos y el diseño actuales y reemplazará al
                                    guardado. ¿Desea continuar?
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
                                    data-test="actualizar-informe-confirm"
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
