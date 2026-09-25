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
import { destroy } from '@/routes/roles';
import type { RoleSummary } from '@/types';

type Props = {
    role: RoleSummary | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteRoleModal({ role, open, onOpenChange }: Props) {
    if (!role) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(role.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar rol?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el rol{' '}
                                    <strong>"{role.name}"</strong>. Solo es
                                    posible si no tiene usuarios asignados.
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
                                    data-test="role-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar rol
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
