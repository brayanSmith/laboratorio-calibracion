import { Form } from '@inertiajs/react';
import EmpresaTerceroController from '@/actions/App/Http/Controllers/EmpresaTerceroController';
import EmpresaTerceroFormFields from '@/components/empresas-terceras/empresa-tercero-form-fields';
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
import type { EmpresaTercero } from '@/types';

type Props = {
    empresaTercero: EmpresaTercero | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditEmpresaTerceroModal({
    empresaTercero,
    open,
    onOpenChange,
}: Props) {
    if (!empresaTercero) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${empresaTercero.id}-${String(open)}`}
                    {...EmpresaTerceroController.update.form(empresaTercero.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Editar empresa tercera
                                </DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos de la empresa tercera
                                </DialogDescription>
                            </DialogHeader>

                            <EmpresaTerceroFormFields
                                empresaTercero={empresaTercero}
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
                                    data-test="empresaTercero-update-submit"
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
