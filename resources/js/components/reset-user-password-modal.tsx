import { Form } from '@inertiajs/react';
import TemporaryPasswordField from '@/components/tenants/temporary-password-field';
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

type Props = {
    user: { id: number; email: string } | null;
    formFor: (userId: number) => { action: string; method: 'post' };
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ResetUserPasswordModal({
    user,
    formFor,
    open,
    onOpenChange,
}: Props) {
    if (!user) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${user.id}-${String(open)}`}
                    {...formFor(user.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Regenerar contraseña temporal
                                </DialogTitle>
                                <DialogDescription>
                                    Se asignará una nueva contraseña temporal a{' '}
                                    <strong>{user.email}</strong> y se cerrarán
                                    sus sesiones abiertas. La contraseña
                                    anterior dejará de funcionar.
                                </DialogDescription>
                            </DialogHeader>

                            <TemporaryPasswordField
                                id="user_password"
                                name="password"
                                error={errors.password}
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
                                    data-test="user-password-submit"
                                >
                                    Asignar contraseña
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
