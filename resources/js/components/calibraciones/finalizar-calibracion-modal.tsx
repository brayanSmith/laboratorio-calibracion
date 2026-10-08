import { Form } from '@inertiajs/react';
import { useState } from 'react';
import CalibracionController from '@/actions/App/Http/Controllers/CalibracionController';
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
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onFinalizado: () => void;
};

/** Modal que se abre al presionar "Finalizar" al editar la calibración: pide el estado
 * final (Finalizado o Devolver a mantenimiento) y cierra el tiempo_servicio en curso al
 * confirmar (ver CalibracionController::finalizar()). */
export default function FinalizarCalibracionModal({
    calibracionId,
    open,
    onOpenChange,
    onFinalizado,
}: Props) {
    const [estadoCalibracion, setEstadoCalibracion] =
        useState<EstadoFinalCalibracion>('FINALIZADO');
    const [firmado, setFirmado] = useState(true);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <Form
                    key={String(open)}
                    {...CalibracionController.finalizar.form(calibracionId)}
                    onSuccess={onFinalizado}
                >
                    {({ processing }) => (
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

                            <input
                                type="hidden"
                                name="estado_calibracion"
                                value={estadoCalibracion}
                            />
                            <input
                                type="hidden"
                                name="firmado"
                                value={firmado ? '1' : '0'}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    disabled={processing}
                                    data-test="confirmar-finalizar-calibracion-button"
                                >
                                    Finalizar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
