import { Form } from '@inertiajs/react';
import { reagendar } from '@/actions/App/Modules/Despachos/Http/Controllers/DespachoController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { hoyLocal } from '@/lib/plazo-servicio';
import type { Despacho } from '@/types';

type Props = {
    despacho: Despacho | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Re-agenda un despacho vencido: pide la nueva fecha y el motivo, que quedan en el
 * historial de re-agendamientos del despacho (se ve en "Ver").
 */
export default function ReagendarDespachoModal({
    despacho,
    open,
    onOpenChange,
}: Props) {
    if (!despacho) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="reagendar-despacho-modal">
                <Form
                    key={`${despacho.id}-${String(open)}`}
                    {...reagendar.form(despacho.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Re-agendar despacho</DialogTitle>
                                <DialogDescription>
                                    {despacho.orden_trabajo_codigo} ·{' '}
                                    {despacho.equipo.codigo}. Estaba programado
                                    para el{' '}
                                    <strong>{despacho.fecha_programada}</strong>
                                    ; el cambio queda en su historial.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="reagendar-fecha">
                                    Nueva fecha programada{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="reagendar-fecha"
                                    name="fecha_programada"
                                    type="date"
                                    min={hoyLocal()}
                                    required
                                    className="w-full sm:w-48"
                                    data-test="reagendar-fecha"
                                />
                                <InputError message={errors.fecha_programada} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reagendar-motivo">
                                    Motivo{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Textarea
                                    id="reagendar-motivo"
                                    name="motivo"
                                    rows={3}
                                    maxLength={500}
                                    required
                                    placeholder="Ej. El cliente no estaba disponible para recibir el equipo"
                                    data-test="reagendar-motivo"
                                />
                                <InputError message={errors.motivo} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    data-test="reagendar-despacho-confirm"
                                >
                                    Re-agendar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
