import { Form } from '@inertiajs/react';
import LaboratorioController from '@/actions/App/Http/Controllers/LaboratorioController';
import LaboratorioFormFields from '@/components/laboratorios/laboratorio-form-fields';
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
import type { Laboratorio } from '@/types';

type Props = {
    laboratorio: Laboratorio | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditLaboratorioModal({
    laboratorio,
    open,
    onOpenChange,
}: Props) {
    if (!laboratorio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${laboratorio.id}-${String(open)}`}
                    {...LaboratorioController.update.form(laboratorio.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar laboratorio</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del laboratorio
                                </DialogDescription>
                            </DialogHeader>

                            <LaboratorioFormFields
                                laboratorio={laboratorio}
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
                                    data-test="laboratorio-update-submit"
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
