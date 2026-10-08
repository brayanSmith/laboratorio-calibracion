import { Form } from '@inertiajs/react';
import DespachoController from '@/actions/App/Http/Controllers/DespachoController';
import DespachoFormFields from '@/components/despachos/despacho-form-fields';
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
import type { Despacho, DespachoOptions } from '@/types';

type Props = {
    despacho: Despacho | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
} & DespachoOptions;

export default function EditDespachoModal({
    despacho,
    tecnicos,
    clientes,
    novedadesDespacho,
    open,
    onOpenChange,
}: Props) {
    if (!despacho) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={`${despacho.id}-${String(open)}`}
                    {...DespachoController.update.form(despacho.id)}
                    className="min-w-0 space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar despacho</DialogTitle>
                                <DialogDescription>
                                    Actualiza quién entrega y recibe el equipo,
                                    y los datos de la entrega
                                </DialogDescription>
                            </DialogHeader>

                            <DespachoFormFields
                                despacho={despacho}
                                tecnicos={tecnicos}
                                clientes={clientes}
                                novedadesDespacho={novedadesDespacho}
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
                                    data-test="despacho-update-submit"
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
