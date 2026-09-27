import { Form } from '@inertiajs/react';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import ClienteFormFields from '@/components/clientes/cliente-form-fields';
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
import type { Cliente } from '@/types';

type Props = {
    cliente: Cliente | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditClienteModal({
    cliente,
    open,
    onOpenChange,
}: Props) {
    if (!cliente) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${cliente.id}-${String(open)}`}
                    {...ClienteController.update.form(cliente.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar cliente</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del cliente
                                </DialogDescription>
                            </DialogHeader>

                            <ClienteFormFields
                                cliente={cliente}
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
                                    data-test="cliente-update-submit"
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
