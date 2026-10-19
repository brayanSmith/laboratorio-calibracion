import { Form } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import EntradaItemController from '@/actions/App/Http/Controllers/EntradaItemController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Item } from '@/types';

type Props = {
    item: Item | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const formatoMoneda = (valor: number) =>
    valor.toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

export default function EntradasPendientesModal({
    item,
    open,
    onOpenChange,
}: Props) {
    if (!item) {
        return null;
    }

    const hoy = new Date().toLocaleDateString('en-CA');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Entradas pendientes</DialogTitle>
                    <DialogDescription>
                        {item.codigo} — {item.nombre}. Marca como recibidas las
                        entradas que ya llegaron.
                    </DialogDescription>
                </DialogHeader>

                {item.entradas_pendientes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Este ítem no tiene entradas pendientes.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {item.entradas_pendientes.map((entrada) => (
                            <li
                                key={entrada.id}
                                className="rounded-lg border p-4"
                                data-test="entrada-pendiente"
                            >
                                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                    <span className="font-medium">
                                        {entrada.cantidad} uds ×{' '}
                                        {formatoMoneda(entrada.precio_unitario)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        Total{' '}
                                        {formatoMoneda(entrada.precio_total)}
                                        {entrada.codigo_compra
                                            ? ` · ${entrada.codigo_compra}`
                                            : ''}
                                    </span>
                                </div>

                                <Form
                                    {...EntradaItemController.recibir.form(
                                        entrada.id,
                                    )}
                                    className="flex flex-wrap items-end gap-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor={`fecha_recibido_${entrada.id}`}
                                                >
                                                    Fecha de recibido
                                                </Label>
                                                <Input
                                                    id={`fecha_recibido_${entrada.id}`}
                                                    name="fecha_recibido"
                                                    type="date"
                                                    defaultValue={hoy}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.fecha_recibido
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test="entrada-recibir-submit"
                                            >
                                                <PackageCheck /> Marcar recibida
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}
