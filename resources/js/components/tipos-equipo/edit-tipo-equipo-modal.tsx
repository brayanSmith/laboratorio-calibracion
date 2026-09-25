import { Form } from '@inertiajs/react';
import TipoEquipoController from '@/actions/App/Http/Controllers/TipoEquipoController';
import TipoEquipoFormFields from '@/components/tipos-equipo/tipo-equipo-form-fields';
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
import type { TipoEquipo } from '@/types';

type Props = {
    tipoEquipo: TipoEquipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditTipoEquipoModal({
    tipoEquipo,
    open,
    onOpenChange,
}: Props) {
    if (!tipoEquipo) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${tipoEquipo.id}-${String(open)}`}
                    {...TipoEquipoController.update.form(tipoEquipo.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar tipo de equipo</DialogTitle>
                                <DialogDescription>
                                    Actualiza el nombre o el tipo de
                                    mantenimiento
                                </DialogDescription>
                            </DialogHeader>

                            <TipoEquipoFormFields
                                tipoEquipo={tipoEquipo}
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
                                    data-test="tipo-equipo-update-submit"
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
