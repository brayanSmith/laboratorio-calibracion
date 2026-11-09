import { Form } from '@inertiajs/react';
import type { ObjetivoUsuario } from '@/components/objetivos/tipos';
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
import { destroy } from '@/routes/objetivos';

type Props = {
    objetivo: ObjetivoUsuario | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EliminarObjetivoUsuarioModal({
    objetivo,
    open,
    onOpenChange,
}: Props) {
    if (!objetivo) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form({
                        usuario: objetivo.user_id,
                        anio: objetivo.anio,
                    })}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar objetivos?</DialogTitle>
                                <DialogDescription>
                                    Se eliminarán los objetivos de
                                    mantenimientos y calibraciones de{' '}
                                    <strong>{objetivo.usuario}</strong> de todos
                                    los meses de {objetivo.anio}.
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
                                    data-test="objetivo-usuario-delete-confirm"
                                    loading={processing}
                                >
                                    Eliminar objetivos
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
