import { Form } from '@inertiajs/react';
import EmpresaController from '@/actions/App/Http/Controllers/EmpresaController';
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

export default function DeleteEmpresaModal({
    empresa,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...EmpresaController.destroy.form()}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar empresa?</DialogTitle>
                                <DialogDescription>
                                    Se eliminará la empresa{' '}
                                    <strong>"{empresa.nombre}"</strong> y su
                                    logo. Después podrás registrar otra.
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
                                    data-test="empresa-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar empresa
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
