import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MantenimientoController from '@/actions/App/Http/Controllers/MantenimientoController';
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
import { useElapsedTime } from '@/hooks/use-elapsed-time';

type EstadoEquipo = 'OPERATIVO' | 'FUERA_DE_SERVICIO';

type Props = {
    mantenimientoId: number;
    iniciadoEn: Date | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onFinalizado: () => void;
};

/** Modal que se abre al presionar "Finalizar" en la gestión del mantenimiento:
 * pide el estado final del equipo y si quedó firmado, y cierra el tiempo_servicio
 * en curso al confirmar (ver MantenimientoController::finalizar()). */
export default function FinalizarMantenimientoModal({
    mantenimientoId,
    iniciadoEn,
    open,
    onOpenChange,
    onFinalizado,
}: Props) {
    const [estadoFinalEquipo, setEstadoFinalEquipo] =
        useState<EstadoEquipo>('OPERATIVO');
    const [firmado, setFirmado] = useState(true);
    const tiempoTranscurrido = useElapsedTime(iniciadoEn ?? new Date());

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <Form
                    {...MantenimientoController.finalizar.form(mantenimientoId)}
                    onSuccess={onFinalizado}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Finalizar mantenimiento
                                </DialogTitle>
                                <DialogDescription>
                                    Tiempo total:{' '}
                                    <span
                                        className="font-mono"
                                        data-test="finalizar-tiempo-total"
                                    >
                                        {tiempoTranscurrido}
                                    </span>
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-2">
                                <div className="space-y-1">
                                    <Label className="text-xs text-muted-foreground">
                                        Estado final del equipo
                                    </Label>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        value={estadoFinalEquipo}
                                        onValueChange={(value) => {
                                            if (value) {
                                                setEstadoFinalEquipo(
                                                    value as EstadoEquipo,
                                                );
                                            }
                                        }}
                                        className="w-full"
                                        data-test="estado-final-equipo-toggle"
                                    >
                                        <ToggleGroupItem
                                            value="OPERATIVO"
                                            className="grow basis-0 data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white"
                                        >
                                            Operativo
                                        </ToggleGroupItem>
                                        <ToggleGroupItem
                                            value="FUERA_DE_SERVICIO"
                                            className="grow basis-0 data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white"
                                        >
                                            Fuera de servicio
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                </div>

                                <div className="space-y-1">
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
                            </div>

                            <input
                                type="hidden"
                                name="estado_final_equipo"
                                value={estadoFinalEquipo}
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
                                    data-test="confirmar-finalizar-button"
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
