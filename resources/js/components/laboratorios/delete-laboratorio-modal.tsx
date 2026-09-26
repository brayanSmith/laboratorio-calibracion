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
import { destroy } from '@/routes/laboratorios';
import type { Laboratorio } from '@/types';

type Props = {
    laboratorio: Laboratorio | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteLaboratorioModal({
    laboratorio,
    open,
    onOpenChange,
}: Props) {
    if (!laboratorio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(laboratorio.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar laboratorio?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el laboratorio{' '}
                                    <strong>"{laboratorio.nombre}"</strong>.
                                    Solo es posible si no tiene calibraciones
                                    asociadas.
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
                                    data-test="laboratorio-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar laboratorio
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
