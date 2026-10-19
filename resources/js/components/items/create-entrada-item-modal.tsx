import { Form } from '@inertiajs/react';
import { useState } from 'react';
import EntradaItemController from '@/actions/App/Modules/Inventario/Http/Controllers/EntradaItemController';
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
import { Textarea } from '@/components/ui/textarea';
import type { Item } from '@/types';

type Props = {
    item: Item | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CreateEntradaItemModal({
    item,
    open,
    onOpenChange,
}: Props) {
    const [recibido, setRecibido] = useState(false);

    const handleOpenChange = (nextOpen: boolean) => {
        setRecibido(false);
        onOpenChange(nextOpen);
    };

    if (!item) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${item.id}-${String(open)}`}
                    {...EntradaItemController.store.form(item.id)}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nueva entrada</DialogTitle>
                                <DialogDescription>
                                    Registra una entrada del ítem {item.codigo}{' '}
                                    — {item.nombre}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="cantidad">Cantidad</Label>
                                    <Input
                                        id="cantidad"
                                        name="cantidad"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                    />
                                    <InputError message={errors.cantidad} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="precio_unitario">
                                        Precio unitario
                                    </Label>
                                    <Input
                                        id="precio_unitario"
                                        name="precio_unitario"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                    <InputError
                                        message={errors.precio_unitario}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="codigo_compra">
                                        Código de compra (opcional)
                                    </Label>
                                    <Input
                                        id="codigo_compra"
                                        name="codigo_compra"
                                    />
                                    <InputError
                                        message={errors.codigo_compra}
                                    />
                                </div>

                                <div className="flex items-center gap-2 sm:col-span-2">
                                    <Checkbox
                                        id="recibido"
                                        name="recibido"
                                        checked={recibido}
                                        onCheckedChange={(checked) =>
                                            setRecibido(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="recibido"
                                        className="font-normal"
                                    >
                                        Ya fue recibido
                                    </Label>
                                    <InputError message={errors.recibido} />
                                </div>

                                {recibido ? (
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="fecha_recibido">
                                            Fecha de recibido
                                        </Label>
                                        <Input
                                            id="fecha_recibido"
                                            name="fecha_recibido"
                                            type="date"
                                            required
                                        />
                                        <InputError
                                            message={errors.fecha_recibido}
                                        />
                                    </div>
                                ) : null}

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="observaciones">
                                        Observaciones (opcional)
                                    </Label>
                                    <Textarea
                                        id="observaciones"
                                        name="observaciones"
                                    />
                                    <InputError
                                        message={errors.observaciones}
                                    />
                                </div>
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
                                    data-test="entrada-item-create-submit"
                                >
                                    Guardar entrada
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
