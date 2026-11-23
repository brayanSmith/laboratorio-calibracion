import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import MantenimientoController from '@/actions/App/Modules/Mantenimientos/Http/Controllers/MantenimientoController';
import FinalizarMantenimientoModal from '@/components/mantenimientos/finalizar-mantenimiento-modal';
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
import { usePermissions } from '@/hooks/use-permissions';
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
 * ítems utilizados, los comentarios y las fotos, con un solo botón de guardar. Si todavía
 * no está finalizado (ej. una devolución desde calibración), "Finalizar" guarda los
 * cambios y abre la confirmación de finalizar.
 */
export default function EditMantenimientoModal({
    mantenimiento,
    tecnicos,
    novedadesMantenimiento,
    items,
    open,
    onOpenChange,
}: Props) {
    const { can } = usePermissions();
    const [finalizarOpen, setFinalizarOpen] = useState(false);
    // Si el envío del formulario vino de "Finalizar": al guardar se abre la confirmación
    // en vez de cerrar el editor.
    const finalizarTrasGuardar = useRef(false);

    if (!mantenimiento) {
        return null;
    }

    const puedeFinalizar =
        can('mantenimientos.finalizar') &&
        mantenimiento.estado_mantenimiento !== 'FINALIZADO';

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <Form
                        key={`${mantenimiento.id}-${String(open)}`}
                        {...MantenimientoController.update.form(
                            mantenimiento.id,
                        )}
                        options={{ preserveState: true }}
                        className="min-w-0 space-y-6"
                        onSuccess={() => {
                            if (finalizarTrasGuardar.current) {
                                setFinalizarOpen(true);

                                return;
                            }

                            onOpenChange(false);
                        }}
                    >
                        {({ errors, processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Editar mantenimiento
                                    </DialogTitle>
                                    <DialogDescription>
                                        Actualiza los datos, el checklist, los
                                        defectos, los ítems utilizados, los
                                        comentarios y las fotos del
                                        mantenimiento
                                    </DialogDescription>
                                </DialogHeader>

                                <MantenimientoFormFields
                                    mantenimiento={mantenimiento}
                                    tecnicos={tecnicos}
                                    novedadesMantenimiento={
                                        novedadesMantenimiento
                                    }
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
                                    {puedeFinalizar ? (
                                        <Button
                                            type="submit"
                                            className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                            disabled={processing}
                                            onClick={() => {
                                                finalizarTrasGuardar.current = true;
                                            }}
                                            data-test="mantenimiento-update-finalizar"
                                        >
                                            Finalizar
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="submit"
                                        loading={processing}
                                        onClick={() => {
                                            finalizarTrasGuardar.current = false;
                                        }}
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

            {finalizarOpen ? (
                <FinalizarMantenimientoModal
                    mantenimientoId={mantenimiento.id}
                    iniciadoEn={null}
                    estadoFinalInicial={mantenimiento.estado_final_equipo}
                    open={finalizarOpen}
                    onOpenChange={setFinalizarOpen}
                    onFinalizado={() => {
                        setFinalizarOpen(false);
                        onOpenChange(false);
                    }}
                />
            ) : null}
        </>
    );
}
