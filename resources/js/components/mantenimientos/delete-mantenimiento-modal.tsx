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
import { destroy } from '@/routes/mantenimientos';
import type { Mantenimiento } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteMantenimientoModal({
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
                    {...destroy.form(mantenimiento.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar mantenimiento?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el mantenimiento de{' '}
                                    <strong>
                                        "{mantenimiento.equipo.codigo}"
                                    </strong>{' '}
                                    de la orden de trabajo{' '}
                                    {mantenimiento.orden_trabajo_codigo}.
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
                                    data-test="mantenimiento-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar mantenimiento
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
