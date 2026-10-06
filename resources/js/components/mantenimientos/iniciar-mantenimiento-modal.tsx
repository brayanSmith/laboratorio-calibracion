import { Form } from '@inertiajs/react';
import MantenimientoController from '@/actions/App/Http/Controllers/MantenimientoController';
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
import type { Mantenimiento } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Se llama justo después de iniciarse, para abrir la modal de gestión. */
    onIniciado: () => void;
};

export default function IniciarMantenimientoModal({
    mantenimiento,
    open,
    onOpenChange,
    onIniciado,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...MantenimientoController.iniciar.form(mantenimiento.id)}
                    onSuccess={() => {
                        onOpenChange(false);
                        onIniciado();
                    }}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Iniciar mantenimiento?
                                </DialogTitle>
                                <DialogDescription>
                                    El mantenimiento se iniciará y se empezará a
                                    medir el tiempo. ¿Desea continuar?
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="iniciar-mantenimiento-confirm"
                                >
                                    Sí, iniciar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
