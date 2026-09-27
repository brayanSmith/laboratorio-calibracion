import { Form } from '@inertiajs/react';
import UnidadMedidaController from '@/actions/App/Http/Controllers/UnidadMedidaController';
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
import UnidadMedidaFormFields from '@/components/unidades-medida/unidad-medida-form-fields';
import type { UnidadMedida } from '@/types';

type Props = {
    unidadMedida: UnidadMedida | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditUnidadMedidaModal({
    unidadMedida,
    open,
    onOpenChange,
}: Props) {
    if (!unidadMedida) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${unidadMedida.id}-${String(open)}`}
                    {...UnidadMedidaController.update.form(unidadMedida.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Editar unidad de medida
                                </DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre o el símbolo
                                </DialogDescription>
                            </DialogHeader>

                            <UnidadMedidaFormFields
                                unidadMedida={unidadMedida}
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
                                    data-test="unidad-medida-update-submit"
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
