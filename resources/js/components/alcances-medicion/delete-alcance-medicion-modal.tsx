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
import { destroy } from '@/routes/alcances-medicion';
import type { AlcanceMedicion } from '@/types';

type Props = {
    alcanceMedicion: AlcanceMedicion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteAlcanceMedicionModal({
    alcanceMedicion,
    open,
    onOpenChange,
}: Props) {
    if (!alcanceMedicion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(alcanceMedicion.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar alcance de medición?
                                </DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el alcance{' '}
                                    <strong>
                                        "{alcanceMedicion.alcance_indicacion}"
                                    </strong>{' '}
                                    de {alcanceMedicion.tipo_equipo} junto con
                                    sus detalles. Solo es posible si no se ha
                                    usado en calibraciones.
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
                                    data-test="alcance-medicion-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar alcance
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
