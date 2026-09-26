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
import { destroy } from '@/routes/areas';
import type { Area } from '@/types';

type Props = {
    area: Area | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteAreaModal({ area, open, onOpenChange }: Props) {
    if (!area) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(area.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar área?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el área{' '}
                                    <strong>"{area.nombre}"</strong>. Solo es
                                    posible si no tiene equipos ni bahías
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
                                    data-test="area-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar área
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
