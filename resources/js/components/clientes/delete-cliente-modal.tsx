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
import { destroy } from '@/routes/clientes';
import type { Cliente } from '@/types';

type Props = {
    cliente: Cliente | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteClienteModal({
    cliente,
    open,
    onOpenChange,
}: Props) {
    if (!cliente) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(cliente.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar cliente?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el cliente{' '}
                                    <strong>"{cliente.nombre}"</strong>. Solo es
                                    posible si no tiene equipos, ingresos ni
                                    despachos asociados.
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
                                    data-test="cliente-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar cliente
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
