import { Form } from '@inertiajs/react';
import MantenimientoController from '@/actions/App/Http/Controllers/MantenimientoController';
import MantenimientoFormFields from '@/components/mantenimientos/mantenimiento-form-fields';
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
import type { Mantenimiento, MantenimientoOption } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    tecnicos: MantenimientoOption[];
    novedadesMantenimiento: MantenimientoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditMantenimientoModal({
    mantenimiento,
    tecnicos,
    novedadesMantenimiento,
    open,
    onOpenChange,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <Form
                    key={`${mantenimiento.id}-${String(open)}`}
                    {...MantenimientoController.update.form(mantenimiento.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar mantenimiento</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del mantenimiento
                                </DialogDescription>
                            </DialogHeader>

                            <MantenimientoFormFields
                                mantenimiento={mantenimiento}
                                tecnicos={tecnicos}
                                novedadesMantenimiento={novedadesMantenimiento}
                                errors={errors}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="mantenimiento-update-submit"
                                >
                                    Guardar cambios
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
