import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CalibracionController from '@/actions/App/Modules/Calibraciones/Http/Controllers/CalibracionController';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type EstadoFinalCalibracion = 'FINALIZADO' | 'DEVOLVER_MANTENIMIENTO';

type Props = {
    calibracionId: number;
    /**
     * Datos del formulario de edición. Si se entrega, se envían junto con la finalización
     * y el servidor los guarda en la misma petición (no hace falta guardar antes).
     */
    obtenerFormulario?: () => FormData | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onFinalizado: () => void;
};

/** Modal que se abre al presionar "Finalizar" al editar la calibración: pide el estado
 * final (Finalizado o Devolver a mantenimiento) y si queda firmada. Al confirmar envía, en
 * una sola petición, el formulario de edición y la finalización: el servidor guarda
 * primero los datos, calcula el resultado (Aprobado o No aprobado) a partir de los
 * resultados de la medición y cierra el tiempo_servicio en curso (ver
 * CalibracionController::finalizar()). */
export default function FinalizarCalibracionModal({
    calibracionId,
    obtenerFormulario,
    open,
    onOpenChange,
    onFinalizado,
}: Props) {
    const [procesando, setProcesando] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    // Los errores del servidor (campos del formulario de edición o de la finalización) se
    // listan sin repetir mensajes.
    const mensajesDeError = [...new Set(Object.values(errores))];
    const [estadoCalibracion, setEstadoCalibracion] =
        useState<EstadoFinalCalibracion>('FINALIZADO');
    const [firmado, setFirmado] = useState(true);

    const finalizar = (evento: FormEvent<HTMLFormElement>) => {
        evento.preventDefault();

        const datos = obtenerFormulario?.() ?? new FormData();
        datos.set('estado_calibracion', estadoCalibracion);
        datos.set('firmado', firmado ? '1' : '0');

        router.post(CalibracionController.finalizar.url(calibracionId), datos, {
            preserveScroll: true,
            onStart: () => {
                setProcesando(true);
                setErrores({});
            },
            onError: (errors) => setErrores(errors),
            onSuccess: onFinalizado,
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form key={String(open)} onSubmit={finalizar}>
                    <>
                        <DialogHeader>
                            <DialogTitle>Finalizar calibración</DialogTitle>
                            <DialogDescription>
                                Selecciona el estado final de la calibración
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-1 py-2">
                            <Label className="text-xs text-muted-foreground">
                                Estado de la calibración
                            </Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={estadoCalibracion}
                                onValueChange={(value) => {
                                    if (value) {
                                        setEstadoCalibracion(
                                            value as EstadoFinalCalibracion,
                                        );
                                    }
                                }}
                                className="w-full"
                                data-test="estado-calibracion-final-toggle"
                            >
                                <ToggleGroupItem
                                    value="DEVOLVER_MANTENIMIENTO"
                                    className="grow basis-0 data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white"
                                >
                                    Devolver a mantenimiento
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="FINALIZADO"
                                    className="grow basis-0 data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white"
                                >
                                    Finalizado
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        {mensajesDeError.length > 0 ? (
                            <ul
                                className="space-y-1 py-2"
                                data-test="finalizar-errores"
                            >
                                {mensajesDeError.map((mensaje) => (
                                    <li key={mensaje}>
                                        <InputError message={mensaje} />
                                    </li>
                                ))}
                            </ul>
                        ) : null}

                        <div className="space-y-1 py-2">
                            <Label className="text-xs text-muted-foreground">
                                Firmado
                            </Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={firmado ? 'SI' : 'NO'}
                                onValueChange={(value) => {
                                    if (value) {
                                        setFirmado(value === 'SI');
                                    }
                                }}
                                className="w-full"
                                data-test="firmado-toggle"
                            >
                                <ToggleGroupItem
                                    value="SI"
                                    className="grow basis-0 data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white"
                                >
                                    Firmado
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="NO"
                                    className="grow basis-0 data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white"
                                >
                                    No firmado
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                disabled={procesando}
                                data-test="confirmar-finalizar-calibracion-button"
                            >
                                Finalizar
                            </Button>
                        </DialogFooter>
                    </>
                </form>
            </DialogContent>
        </Dialog>
    );
}
