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
import { destroy } from '@/routes/tipos-equipo';
import type { TipoEquipo } from '@/types';

type Props = {
    tipoEquipo: TipoEquipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteTipoEquipoModal({
    tipoEquipo,
    open,
    onOpenChange,
}: Props) {
    if (!tipoEquipo) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(tipoEquipo.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar tipo de equipo?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el tipo de equipo{' '}
                                    <strong>"{tipoEquipo.nombre}"</strong>. Solo
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
                                    data-test="tipo-equipo-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar tipo de equipo
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
