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
import { destroy } from '@/routes/ingresos';
import type { Ingreso } from '@/types';

type Props = {
    ingreso: Ingreso | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteIngresoModal({
    ingreso,
    open,
    onOpenChange,
}: Props) {
    if (!ingreso) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(ingreso.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar ingreso?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el ingreso
                                    {ingreso.cliente_entrega_nombre ? (
                                        <>
                                            {' '}
                                            de{' '}
                                            <strong>
                                                "
                                                {ingreso.cliente_entrega_nombre}
                                                "
                                            </strong>
                                        </>
                                    ) : null}{' '}
                                    del {ingreso.desde}. Solo es posible si no
                                    tiene órdenes de trabajo asociadas.
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
                                    data-test="ingreso-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar ingreso
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
