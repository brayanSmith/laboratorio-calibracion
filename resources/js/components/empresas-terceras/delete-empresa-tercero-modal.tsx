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
import { destroy } from '@/routes/empresas-terceras';
import type { EmpresaTercero } from '@/types';

type Props = {
    empresaTercero: EmpresaTercero | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteEmpresaTerceroModal({
    empresaTercero,
    open,
    onOpenChange,
}: Props) {
    if (!empresaTercero) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(empresaTercero.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar empresa tercera?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará la empresa tercera{' '}
                                    <strong>"{empresaTercero.nombre}"</strong>.
                                    Solo es posible si no tiene servicios
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
                                    data-test="empresaTercero-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar empresa tercera
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
