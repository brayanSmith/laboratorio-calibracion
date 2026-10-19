import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { usePermissions } from '@/hooks/use-permissions';
import CalibracionController from '@/actions/App/Modules/Calibraciones/Http/Controllers/CalibracionController';
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
import { valoresGuardados } from '@/lib/calibracion-resultado';
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
    laboratorioPrincipal,
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
                    laboratorioPrincipal={laboratorioPrincipal}
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
    laboratorioPrincipal,
    procedimientos,
    novedadesCalibracion,
    iniciadoEn,
    onSuccess,
}: FormularioProps) {
    const tiempoTranscurrido = useElapsedTime(iniciadoEn ?? new Date());
    const [finalizarOpen, setFinalizarOpen] = useState(false);
    // Lo escrito en "Valor instrumento": alimenta en vivo el resultado de la calibración.
    const [valoresInstrumento, setValoresInstrumento] = useState(() =>
        valoresGuardados(calibracion.detalles_medicion),
    );
    const { can } = usePermissions();
    // Marca dentro del formulario: permite a "Finalizar" leer sus datos y enviarlos junto
    // con la finalización, en una sola petición (ver FinalizarCalibracionModal).
    const marcaFormulario = useRef<HTMLSpanElement>(null);
    const puedeEditar = can('calibraciones.editar');

    const obtenerFormulario = (): FormData | null => {
        const formulario = marcaFormulario.current?.closest('form');

        return formulario ? new FormData(formulario) : null;
    };

    // Antes de abrir la finalización se valida el formulario (campos obligatorios).
    const abrirFinalizar = () => {
        const formulario = marcaFormulario.current?.closest('form');

        if (puedeEditar && formulario && !formulario.reportValidity()) {
            return;
        }

        setFinalizarOpen(true);
    };

    return (
        <>
            <Form
                {...CalibracionController.update.form(calibracion.id)}
                className="min-w-0 space-y-6"
                onSuccess={onSuccess}
            >
                {({ errors, processing }) => (
                    <>
                        <span ref={marcaFormulario} hidden />
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
                            laboratorioPrincipal={laboratorioPrincipal}
                            valoresInstrumento={valoresInstrumento}
                            onValorInstrumentoChange={(detalleId, valor) =>
                                setValoresInstrumento((prev) => ({
                                    ...prev,
                                    [detalleId]: valor,
                                }))
                            }
                            procedimientos={procedimientos}
                            novedadesCalibracion={novedadesCalibracion}
                            errors={errors}
                        />

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancelar</Button>
                            </DialogClose>
                            {can('calibraciones.finalizar') ? (
                                <Button
                                    type="button"
                                    className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    disabled={processing}
                                    onClick={abrirFinalizar}
                                    data-test="abrir-finalizar-calibracion-button"
                                >
                                    Finalizar
                                </Button>
                            ) : null}
                            <Button
                                type="submit"
                                loading={processing}
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
                obtenerFormulario={puedeEditar ? obtenerFormulario : undefined}
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
