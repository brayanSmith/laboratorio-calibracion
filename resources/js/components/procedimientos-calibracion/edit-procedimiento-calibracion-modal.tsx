import { Form } from '@inertiajs/react';
import ProcedimientoCalibracionController from '@/actions/App/Http/Controllers/ProcedimientoCalibracionController';
import ProcedimientoCalibracionFormFields from '@/components/procedimientos-calibracion/procedimiento-calibracion-form-fields';
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
import type { ProcedimientoCalibracion } from '@/types';

type Props = {
    procedimientoCalibracion: ProcedimientoCalibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditProcedimientoCalibracionModal({
    procedimientoCalibracion,
    open,
    onOpenChange,
}: Props) {
    if (!procedimientoCalibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${procedimientoCalibracion.id}-${String(open)}`}
                    {...ProcedimientoCalibracionController.update.form(
                        procedimientoCalibracion.id,
                    )}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Editar procedimiento de calibración
                                </DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre del procedimiento
                                </DialogDescription>
                            </DialogHeader>

                            <ProcedimientoCalibracionFormFields
                                procedimientoCalibracion={
                                    procedimientoCalibracion
                                }
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
                                    data-test="procedimiento-calibracion-update-submit"
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
