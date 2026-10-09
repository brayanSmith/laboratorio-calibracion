import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ServicioTerceroController from '@/actions/App/Http/Controllers/ServicioTerceroController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import EquipoServicioCard from '@/components/servicio-terceros/equipo-servicio-card';
import { useElapsedTime } from '@/hooks/use-elapsed-time';
import type { ServicioTerceroListado } from '@/types';

type EstadoFinal = 'APROBADO' | 'RECHAZADO';

type Props = {
    servicio: ServicioTerceroListado | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Modal que se abre al presionar "Finalizar" en un servicio de tercero: pide
 * el documento (PDF) del servicio, el estado final del equipo y, si quedó
 * rechazado, si se re-agenda (ver ServicioTerceroController::finalizar()). */
export default function FinalizarServicioTerceroModal({
    servicio,
    open,
    onOpenChange,
}: Props) {
    const [estadoFinalEquipo, setEstadoFinalEquipo] =
        useState<EstadoFinal>('APROBADO');
    const [reAgendar, setReAgendar] = useState(false);
    const tiempoTranscurrido = useElapsedTime(
        servicio?.tiempo_servicio_inicio
            ? new Date(servicio.tiempo_servicio_inicio)
            : new Date(),
    );

    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <Form
                    key={`${servicio.id}-${String(open)}`}
                    {...ServicioTerceroController.finalizar.form(servicio.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Finalizar servicio de tercero
                                </DialogTitle>
                                <DialogDescription>
                                    {servicio.orden_trabajo_codigo} ·{' '}
                                    {servicio.equipo_codigo}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-2">
                                <div
                                    className="flex items-center justify-between gap-4 rounded-lg border bg-muted/40 p-4"
                                    data-test="finalizar-servicio-tarjeta"
                                >
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Recibiendo de
                                        </p>
                                        <p className="truncate font-medium">
                                            {servicio.empresa_tercero_nombre}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs text-muted-foreground">
                                            Tiempo total
                                        </p>
                                        <p
                                            className="font-mono text-lg"
                                            data-test="finalizar-tiempo-total"
                                        >
                                            {tiempoTranscurrido}
                                        </p>
                                    </div>
                                </div>

                                <EquipoServicioCard servicio={servicio} />

                                <div className="grid gap-2">
                                    <Label htmlFor="pdf_servicio">
                                        Documentación del servicio (PDF)
                                    </Label>
                                    <Input
                                        id="pdf_servicio"
                                        name="pdf_servicio"
                                        type="file"
                                        accept="application/pdf"
                                        data-test="servicio-tercero-pdf-input"
                                    />
                                    <InputError message={errors.pdf_servicio} />
                                </div>

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
                                                    value as EstadoFinal,
                                                );
                                            }
                                        }}
                                        className="w-full"
                                        data-test="estado-final-equipo-toggle"
                                    >
                                        <ToggleGroupItem
                                            value="APROBADO"
                                            className="grow basis-0 data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white"
                                        >
                                            Aprobado
                                        </ToggleGroupItem>
                                        <ToggleGroupItem
                                            value="RECHAZADO"
                                            className="grow basis-0 data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white"
                                        >
                                            Rechazado
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                </div>

                                {estadoFinalEquipo === 'RECHAZADO' ? (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="re_agendar"
                                            checked={reAgendar}
                                            onCheckedChange={(checked) =>
                                                setReAgendar(checked === true)
                                            }
                                            data-test="re-agendar-checkbox"
                                        />
                                        <Label htmlFor="re_agendar">
                                            Re-agendar (crear una nueva orden de
                                            trabajo)
                                        </Label>
                                    </div>
                                ) : null}
                            </div>

                            <input
                                type="hidden"
                                name="estado_final_equipo"
                                value={estadoFinalEquipo}
                            />
                            <input
                                type="hidden"
                                name="re_agendar"
                                value={
                                    estadoFinalEquipo === 'RECHAZADO' &&
                                    reAgendar
                                        ? '1'
                                        : '0'
                                }
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
                                    data-test="confirmar-finalizar-servicio-button"
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
