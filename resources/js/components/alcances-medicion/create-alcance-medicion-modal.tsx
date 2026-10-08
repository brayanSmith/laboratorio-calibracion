import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import MedicionAlcanceController from '@/actions/App/Http/Controllers/MedicionAlcanceController';
import AlcanceMedicionFormFields from '@/components/alcances-medicion/alcance-medicion-form-fields';
import DetallesAlcanceTabla from '@/components/alcances-medicion/detalles-alcance-tabla';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    AlcanceMedicionOpcion,
    AlcanceMedicionUnidadOpcion,
} from '@/types';

type Props = PropsWithChildren<{
    tiposEquipo: AlcanceMedicionOpcion[];
    alcancesIndicacion: Record<string, string[]>;
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
}>;

export default function CreateAlcanceMedicionModal({
    tiposEquipo,
    alcancesIndicacion,
    unidadesMedida,
    children,
}: Props) {
    const [open, setOpen] = useState(false);
    // Cada fila lleva una llave estable para que quitar una no mezcle los valores de las demás.
    const [detalleKeys, setDetalleKeys] = useState<number[]>([]);
    const [nextKey, setNextKey] = useState(0);

    const handleOpenChange = (value: boolean) => {
        setOpen(value);
        setDetalleKeys([]);
    };

    const agregarDetalle = () => {
        setDetalleKeys((keys) => [...keys, nextKey]);
        setNextKey((key) => key + 1);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
                <Form
                    key={String(open)}
                    {...MedicionAlcanceController.store.form()}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Nuevo alcance de medición
                                </DialogTitle>
                                <DialogDescription>
                                    Registra el alcance y, si quieres, sus
                                    detalles
                                </DialogDescription>
                            </DialogHeader>

                            <AlcanceMedicionFormFields
                                tiposEquipo={tiposEquipo}
                                alcancesIndicacion={alcancesIndicacion}
                                errors={errors}
                            />

                            <section className="space-y-3">
                                <p className="text-sm font-medium">Detalles</p>

                                <DetallesAlcanceTabla
                                    filas={detalleKeys.map((key) => ({ key }))}
                                    unidadesMedida={unidadesMedida}
                                    errors={errors}
                                    onRemove={(key) =>
                                        setDetalleKeys((keys) =>
                                            keys.filter((k) => k !== key),
                                        )
                                    }
                                />

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={agregarDetalle}
                                    data-test="alcance-medicion-add-detalle"
                                >
                                    <Plus className="h-4 w-4" /> Agregar otro
                                    detalle
                                </Button>
                            </section>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="alcance-medicion-create-submit"
                                >
                                    Guardar alcance
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
