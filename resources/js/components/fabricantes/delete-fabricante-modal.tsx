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
import { destroy } from '@/routes/fabricantes';
import type { Fabricante } from '@/types';

type Props = {
    fabricante: Fabricante | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteFabricanteModal({
    fabricante,
    open,
    onOpenChange,
}: Props) {
    if (!fabricante) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(fabricante.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar fabricante?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el fabricante{' '}
                                    <strong>"{fabricante.nombre}"</strong>. Solo
                                    es posible si no tiene equipos asociados.
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
                                    data-test="fabricante-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar fabricante
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
