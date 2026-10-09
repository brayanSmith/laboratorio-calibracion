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

export default function DeleteServicioTerceroModal({
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
                    {...ServicioTerceroController.destroy.form(servicio.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar servicio de tercero?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el servicio de{' '}
                                    <strong>"{servicio.equipo_codigo}"</strong>{' '}
                                    de la orden de trabajo{' '}
                                    {servicio.orden_trabajo_codigo}.
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
                                    data-test="servicio-tercero-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar servicio
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
