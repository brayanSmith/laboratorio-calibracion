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
import { destroy } from '@/routes/equipos';
import type { Equipo } from '@/types';

type Props = {
    equipo: Equipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteEquipoModal({
    equipo,
    open,
    onOpenChange,
}: Props) {
    if (!equipo) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(equipo.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar equipo?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el equipo{' '}
                                    <strong>"{equipo.codigo}"</strong> de la
                                    lista. No se puede deshacer desde esta
                                    pantalla.
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
                                    data-test="equipo-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar equipo
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
