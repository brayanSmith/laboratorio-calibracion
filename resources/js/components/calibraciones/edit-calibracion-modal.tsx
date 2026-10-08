import { Form } from '@inertiajs/react';
import { useState } from 'react';
import CalibracionController from '@/actions/App/Http/Controllers/CalibracionController';
import CalibracionFormFields from '@/components/calibraciones/calibracion-form-fields';
import FinalizarCalibracionModal from '@/components/calibraciones/finalizar-calibracion-modal';
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
import { useElapsedTime } from '@/hooks/use-elapsed-time';
import type { Calibracion, CalibracionOptions } from '@/types';

type Props = {
    calibracion: Calibracion | null;
    /** Momento en que se inició el tiempo de servicio, para el cronómetro en vivo. */
    iniciadoEn: Date | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
} & CalibracionOptions;

export default function EditCalibracionModal({
    calibracion,
    tecnicos,
    laboratorios,
    areas,
    procedimientos,
    novedadesCalibracion,
    iniciadoEn,
    open,
    onOpenChange,
}: Props) {
    if (!calibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <EditCalibracionFormulario
                    key={`${calibracion.id}-${String(open)}`}
                    calibracion={calibracion}
                    tecnicos={tecnicos}
                    laboratorios={laboratorios}
                    areas={areas}
                    procedimientos={procedimientos}
                    novedadesCalibracion={novedadesCalibracion}
                    iniciadoEn={iniciadoEn}
                    onSuccess={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

type FormularioProps = {
    calibracion: Calibracion;
    iniciadoEn: Date | null;
    onSuccess: () => void;
} & CalibracionOptions;

function EditCalibracionFormulario({
    calibracion,
    tecnicos,
    laboratorios,
    areas,
    procedimientos,
    novedadesCalibracion,
    iniciadoEn,
    onSuccess,
}: FormularioProps) {
    const tiempoTranscurrido = useElapsedTime(iniciadoEn ?? new Date());
    const [finalizarOpen, setFinalizarOpen] = useState(false);

    return (
        <>
            <Form
                {...CalibracionController.update.form(calibracion.id)}
                className="min-w-0 space-y-6"
                onSuccess={onSuccess}
            >
                {({ errors, processing }) => (
                    <>
                        <DialogHeader>
                            <DialogTitle>Editar calibración</DialogTitle>
                            <DialogDescription>
                                {iniciadoEn ? (
                                    <>
                                        En curso desde hace{' '}
                                        <span
                                            className="font-mono"
                                            data-test="tiempo-transcurrido"
                                        >
                                            {tiempoTranscurrido}
                                        </span>
                                    </>
                                ) : (
                                    'Actualiza los datos de la calibración'
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <CalibracionFormFields
                            calibracion={calibracion}
                            tecnicos={tecnicos}
                            laboratorios={laboratorios}
                            areas={areas}
                            procedimientos={procedimientos}
                            novedadesCalibracion={novedadesCalibracion}
                            errors={errors}
                        />

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancelar</Button>
                            </DialogClose>
                            <Button
                                type="button"
                                className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                onClick={() => setFinalizarOpen(true)}
                                data-test="abrir-finalizar-calibracion-button"
                            >
                                Finalizar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="calibracion-update-submit"
                            >
                                Guardar cambios
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>

            <FinalizarCalibracionModal
                calibracionId={calibracion.id}
                open={finalizarOpen}
                onOpenChange={setFinalizarOpen}
                onFinalizado={() => {
                    setFinalizarOpen(false);
                    onSuccess();
                }}
            />
        </>
    );
}
