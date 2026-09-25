import { Form } from '@inertiajs/react';
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
import { destroy } from '@/routes/plataforma/tenants';
import type { Tenant } from '@/types';

type Props = {
    tenant: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteTenantModal({
    tenant,
    open,
    onOpenChange,
}: Props) {
    if (!tenant) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(tenant.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar tenant?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el tenant{' '}
                                    <strong>"{tenant.nombre}"</strong>. Solo es
                                    posible si no tiene usuarios asociados; si
                                    quieres suspenderlo, desactívalo desde la
                                    edición.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="tenant-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar tenant
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
