import { Form } from '@inertiajs/react';
import EmpresaController from '@/actions/App/Http/Controllers/EmpresaController';
import EmpresaFormFields from '@/components/empresa/empresa-form-fields';
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
import type { Empresa } from '@/types';

type Props = {
    empresa: Empresa;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditEmpresaModal({
    empresa,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={`${empresa.id}-${String(open)}`}
                    {...EmpresaController.update.form()}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar empresa</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos de tu empresa.
                                </DialogDescription>
                            </DialogHeader>

                            <EmpresaFormFields
                                empresa={empresa}
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
                                    data-test="empresa-update-submit"
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
