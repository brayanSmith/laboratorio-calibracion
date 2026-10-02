import { Form } from '@inertiajs/react';
import IngresoController from '@/actions/App/Http/Controllers/IngresoController';
import IngresoFormFields from '@/components/ingresos/ingreso-form-fields';
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
import type { Ingreso, IngresoOptions } from '@/types';

type Props = {
    ingreso: Ingreso | null;
    options: IngresoOptions;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditIngresoModal({
    ingreso,
    options,
    open,
    onOpenChange,
}: Props) {
    if (!ingreso) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={`${ingreso.id}-${String(open)}`}
                    {...IngresoController.update.form(ingreso.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar ingreso</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del ingreso
                                </DialogDescription>
                            </DialogHeader>

                            <IngresoFormFields
                                ingreso={ingreso}
                                options={options}
                                errors={errors}
                                mostrarBuscador
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
                                    data-test="ingreso-update-submit"
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
