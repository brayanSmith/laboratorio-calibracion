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
import { destroy } from '@/routes/bahias';
import type { Bahia } from '@/types';

type Props = {
    bahia: Bahia | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteBahiaModal({ bahia, open, onOpenChange }: Props) {
    if (!bahia) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(bahia.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar bahía?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará la bahía{' '}
                                    <strong>"{bahia.nombre}"</strong>. Solo es
                                    posible si no tiene equipos ni ingresos
                                    asociados.
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
                                    data-test="bahia-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar bahía
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
