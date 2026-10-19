import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import MedicionAlcanceController from '@/actions/App/Modules/AlcancesMedicion/Http/Controllers/MedicionAlcanceController';
import AlcanceMedicionFormFields from '@/components/alcances-medicion/alcance-medicion-form-fields';
import DetallesAlcanceTabla from '@/components/alcances-medicion/detalles-alcance-tabla';
import type { FilaDetalleAlcance } from '@/components/alcances-medicion/detalles-alcance-tabla';
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
    AlcanceMedicion,
    AlcanceMedicionOpcion,
    AlcancesIndicacionPorModelo,
    AlcanceMedicionUnidadOpcion,
    EspecificacionAlcance,
    EspecificacionesAlcance,
} from '@/types';

type Props = {
    alcanceMedicion: AlcanceMedicion | null;
    tiposEquipo: AlcanceMedicionOpcion[];
    alcancesIndicacion: AlcancesIndicacionPorModelo;
    especificaciones: EspecificacionesAlcance;
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type FormProps = Omit<Props, 'alcanceMedicion' | 'open'> & {
    alcanceMedicion: AlcanceMedicion;
};

/** Se monta al abrir el modal, así las filas parten siempre de los detalles guardados. */
function EditAlcanceMedicionForm({
    alcanceMedicion,
    tiposEquipo,
    alcancesIndicacion,
    especificaciones,
    unidadesMedida,
    onOpenChange,
}: FormProps) {
    const [especificacion, setEspecificacion] =
        useState<EspecificacionAlcance | null>(null);
    const siguienteKey = useRef(alcanceMedicion.detalles.length);
    const [filas, setFilas] = useState<FilaDetalleAlcance[]>(() =>
        alcanceMedicion.detalles.map((detalle, key) => ({ key, detalle })),
    );

    return (
        <Form
            {...MedicionAlcanceController.update.form(alcanceMedicion.id)}
            className="space-y-6"
            onSuccess={() => onOpenChange(false)}
        >
            {({ errors, processing }) => (
                <>
                    <DialogHeader>
                        <DialogTitle>Editar alcance de medición</DialogTitle>
                        <DialogDescription>
                            Actualiza el alcance y sus detalles
                        </DialogDescription>
                    </DialogHeader>

                    <input
                        type="hidden"
                        name="sincronizar_detalles"
                        value="1"
                    />

                    <AlcanceMedicionFormFields
                        alcanceMedicion={alcanceMedicion}
                        tiposEquipo={tiposEquipo}
                        alcancesIndicacion={alcancesIndicacion}
                        especificaciones={especificaciones}
                        onEspecificacionChange={setEspecificacion}
                        errors={errors}
                    />

                    <section className="space-y-3">
                        <p className="text-sm font-medium">Detalles</p>

                        <DetallesAlcanceTabla
                            filas={filas}
                            unidadesMedida={unidadesMedida}
                            especificacion={especificacion}
                            errors={errors}
                            onRemove={(key) =>
                                setFilas((prev) =>
                                    prev.filter((fila) => fila.key !== key),
                                )
                            }
                        />

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setFilas((prev) => [
                                    ...prev,
                                    { key: siguienteKey.current++ },
                                ])
                            }
                            data-test="alcance-medicion-add-detalle"
                        >
                            <Plus className="h-4 w-4" /> Agregar otro detalle
                        </Button>
                    </section>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancelar</Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            loading={processing}
                            data-test="alcance-medicion-update-submit"
                        >
                            Guardar cambios
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export default function EditAlcanceMedicionModal({
    alcanceMedicion,
    open,
    onOpenChange,
    ...rest
}: Props) {
    if (!alcanceMedicion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
                <EditAlcanceMedicionForm
                    alcanceMedicion={alcanceMedicion}
                    onOpenChange={onOpenChange}
                    {...rest}
                />
            </DialogContent>
        </Dialog>
    );
}
