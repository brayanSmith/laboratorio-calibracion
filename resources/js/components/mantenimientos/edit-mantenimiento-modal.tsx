import { Form } from '@inertiajs/react';
import MantenimientoController from '@/actions/App/Modules/Mantenimientos/Http/Controllers/MantenimientoController';
import MantenimientoChecklist from '@/components/mantenimientos/mantenimiento-checklist';
import MantenimientoComentarios from '@/components/mantenimientos/mantenimiento-comentarios';
import MantenimientoDefectos from '@/components/mantenimientos/mantenimiento-defectos';
import MantenimientoFormFields from '@/components/mantenimientos/mantenimiento-form-fields';
import MantenimientoGaleria from '@/components/mantenimientos/mantenimiento-galeria';
import MantenimientoItemsUsados from '@/components/mantenimientos/mantenimiento-items-usados';
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
import type {
    Mantenimiento,
    MantenimientoItemOption,
    MantenimientoOption,
} from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    tecnicos: MantenimientoOption[];
    novedadesMantenimiento: MantenimientoOption[];
    items: MantenimientoItemOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Edita todo el mantenimiento: sus datos, el checklist, los defectos identificados, los
 * ítems utilizados, los comentarios y las fotos, con un solo botón de guardar.
 */
export default function EditMantenimientoModal({
    mantenimiento,
    tecnicos,
    novedadesMantenimiento,
    items,
    open,
    onOpenChange,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <Form
                    key={`${mantenimiento.id}-${String(open)}`}
                    {...MantenimientoController.update.form(mantenimiento.id)}
                    className="min-w-0 space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar mantenimiento</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos, el checklist, los
                                    defectos, los ítems utilizados, los
                                    comentarios y las fotos del mantenimiento
                                </DialogDescription>
                            </DialogHeader>

                            <MantenimientoFormFields
                                mantenimiento={mantenimiento}
                                tecnicos={tecnicos}
                                novedadesMantenimiento={novedadesMantenimiento}
                                errors={errors}
                            />

                            <MantenimientoChecklist
                                checklist={mantenimiento.checklist}
                            />

                            <MantenimientoDefectos
                                defectos={mantenimiento.defectos}
                                errors={errors}
                            />

                            <MantenimientoItemsUsados
                                mantenimientoId={mantenimiento.id}
                                itemsUsados={mantenimiento.items_usados}
                                items={items}
                                errors={errors}
                                onSinStockChange={() => {}}
                            />

                            <MantenimientoComentarios
                                comentarios={mantenimiento.comentarios}
                                errors={errors}
                            />

                            <MantenimientoGaleria
                                galeria={mantenimiento.galeria}
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
                                    loading={processing}
                                    data-test="mantenimiento-update-submit"
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
