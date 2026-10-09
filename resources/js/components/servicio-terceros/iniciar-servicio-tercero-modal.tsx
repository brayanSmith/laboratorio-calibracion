import { Form } from '@inertiajs/react';
import ServicioTerceroController from '@/actions/App/Http/Controllers/ServicioTerceroController';
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
import type { ServicioTerceroListado } from '@/types';

type Props = {
    servicio: ServicioTerceroListado | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function IniciarServicioTerceroModal({
    servicio,
    open,
    onOpenChange,
}: Props) {
    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...ServicioTerceroController.iniciar.form(servicio.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Iniciar servicio de tercero?
                                </DialogTitle>
                                <DialogDescription>
                                    El servicio se iniciará y se empezará a
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
                                    data-test="iniciar-servicio-tercero-confirm"
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
