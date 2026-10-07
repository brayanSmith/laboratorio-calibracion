import { Form } from '@inertiajs/react';
import CalibracionController from '@/actions/App/Http/Controllers/CalibracionController';
import CalibracionFormFields from '@/components/calibraciones/calibracion-form-fields';
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
import type { Calibracion, CalibracionOptions } from '@/types';

type Props = {
    calibracion: Calibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
} & CalibracionOptions;

export default function EditCalibracionModal({
    calibracion,
    tecnicos,
    laboratorios,
    areas,
    procedimientos,
    novedadesCalibracion,
    open,
    onOpenChange,
}: Props) {
    if (!calibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <Form
                    key={`${calibracion.id}-${String(open)}`}
                    {...CalibracionController.update.form(calibracion.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar calibración</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos de la calibración
                                </DialogDescription>
                            </DialogHeader>

                            <CalibracionFormFields
                                calibracion={calibracion}
                                tecnicos={tecnicos}
                                laboratorios={laboratorios}
                                areas={areas}
                                procedimientos={procedimientos}
                                novedadesCalibracion={novedadesCalibracion}
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
                                    data-test="calibracion-update-submit"
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
