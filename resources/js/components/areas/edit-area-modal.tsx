import { Form } from '@inertiajs/react';
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import AreaFormFields from '@/components/areas/area-form-fields';
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
import type { Area } from '@/types';

type Props = {
    area: Area | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditAreaModal({ area, open, onOpenChange }: Props) {
    if (!area) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${area.id}-${String(open)}`}
                    {...AreaController.update.form(area.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar área</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del área
                                </DialogDescription>
                            </DialogHeader>

                            <AreaFormFields area={area} errors={errors} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="area-update-submit"
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
