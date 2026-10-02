import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { actualizarEstado } from '@/actions/App/Http/Controllers/IngresoController';
import Combobox from '@/components/combobox';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { estadosIngreso } from '@/lib/estados-ingreso';
import type { EstadoIngreso, Ingreso, IngresoOption } from '@/types';

type Props = {
    ingreso: Ingreso | null;
    tecnicos: IngresoOption[];
    clientes: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function toComboboxOptions(options: IngresoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/** Pendiente en amarillo, aprobado en verde, cancelado en rojo. */
const colorToggleEstadoIngreso: Record<EstadoIngreso, string> = {
    PENDIENTE: 'data-[state=on]:!bg-amber-500 data-[state=on]:!text-white',
    INGRESADO: 'data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white',
    CANCELADO: 'data-[state=on]:!bg-red-500 data-[state=on]:!text-white',
};

/**
 * Recibir, cancelar o devolver a pendiente un ingreso. El estado se elige con un
 * toggle group de colores; según la opción, aparecen los campos que le corresponden:
 * técnico/cliente/firma/novedad si queda Aprobado, el motivo si queda Cancelado, o
 * nada más si vuelve a Pendiente.
 */
export default function RecibirIngresoModal({
    ingreso,
    tecnicos,
    clientes,
    open,
    onOpenChange,
}: Props) {
    const [estadoIngreso, setEstadoIngreso] = useState<EstadoIngreso>(
        ingreso?.estado_ingreso ?? 'PENDIENTE',
    );
    const [tecnicoId, setTecnicoId] = useState<string | undefined>(undefined);
    const [clienteId, setClienteId] = useState<string | undefined>(undefined);
    const aprobado = estadoIngreso === 'INGRESADO';
    const cancelado = estadoIngreso === 'CANCELADO';

    if (!ingreso) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <Form
                    key={`${ingreso.id}-${String(open)}`}
                    {...actualizarEstado.form(ingreso.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Recibir equipos</DialogTitle>
                                <DialogDescription>
                                    Define el estado del ingreso y completa lo
                                    que corresponda
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label>Estado del ingreso</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={estadoIngreso}
                                    onValueChange={(valor) => {
                                        if (valor) {
                                            setEstadoIngreso(
                                                valor as EstadoIngreso,
                                            );
                                        }
                                    }}
                                    className="w-full"
                                >
                                    {estadosIngreso.map((opcion) => (
                                        <ToggleGroupItem
                                            key={opcion.value}
                                            value={opcion.value}
                                            className={`flex-1 ${colorToggleEstadoIngreso[opcion.value as EstadoIngreso]}`}
                                        >
                                            {opcion.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                                <input
                                    type="hidden"
                                    name="estado_ingreso"
                                    value={estadoIngreso}
                                />
                                <InputError message={errors.estado_ingreso} />
                            </div>

                            {aprobado ? (
                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tecnico_recibe_id">
                                            Técnico que recibe
                                        </Label>
                                        <Combobox
                                            id="tecnico_recibe_id"
                                            name="tecnico_recibe_id"
                                            value={
                                                tecnicoId ??
                                                ingreso.tecnico_recibe_id?.toString()
                                            }
                                            onValueChange={setTecnicoId}
                                            options={toComboboxOptions(
                                                tecnicos,
                                            )}
                                            placeholder="Selecciona un técnico"
                                            searchPlaceholder="Buscar técnico..."
                                        />
                                        <InputError
                                            message={errors.tecnico_recibe_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="cliente_entrega_id">
                                            Cliente que entrega
                                        </Label>
                                        <Combobox
                                            id="cliente_entrega_id"
                                            name="cliente_entrega_id"
                                            value={
                                                clienteId ??
                                                ingreso.cliente_entrega_id?.toString()
                                            }
                                            onValueChange={setClienteId}
                                            options={toComboboxOptions(
                                                clientes,
                                            )}
                                            placeholder="Selecciona un cliente"
                                            searchPlaceholder="Buscar cliente..."
                                        />
                                        <InputError
                                            message={errors.cliente_entrega_id}
                                        />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="firma_cliente_entrega">
                                            Firma del cliente
                                        </Label>

                                        {ingreso.firma_url ? (
                                            <img
                                                src={ingreso.firma_url}
                                                alt="Firma actual del cliente"
                                                className="h-16 w-fit rounded-md border bg-white object-contain p-1"
                                                data-test="firma-preview"
                                            />
                                        ) : null}

                                        <Input
                                            id="firma_cliente_entrega"
                                            name="firma_cliente_entrega"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Imagen PNG, JPG o WebP de máximo 2
                                            MB.
                                            {ingreso.firma_url
                                                ? ' Si eliges una nueva, reemplaza a la actual.'
                                                : ''}
                                        </p>
                                        <InputError
                                            message={
                                                errors.firma_cliente_entrega
                                            }
                                        />

                                        {ingreso.firma_url ? (
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    id="eliminar_firma"
                                                    name="eliminar_firma"
                                                />
                                                <Label htmlFor="eliminar_firma">
                                                    Quitar firma actual
                                                </Label>
                                            </div>
                                        ) : null}
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="novedad">
                                            Novedad (opcional)
                                        </Label>
                                        <Textarea
                                            id="novedad"
                                            name="novedad"
                                            defaultValue={ingreso.novedad ?? ''}
                                            placeholder="Describe cualquier novedad del ingreso"
                                        />
                                        <InputError message={errors.novedad} />
                                    </div>
                                </div>
                            ) : null}

                            {cancelado ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="motivo_cancelacion">
                                        Motivo de cancelación
                                    </Label>
                                    <Textarea
                                        id="motivo_cancelacion"
                                        name="motivo_cancelacion"
                                        defaultValue={
                                            ingreso.motivo_cancelacion ?? ''
                                        }
                                        placeholder="Explica por qué se cancela el ingreso"
                                    />
                                    <InputError
                                        message={errors.motivo_cancelacion}
                                    />
                                </div>
                            ) : null}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="ingreso-recibir-submit"
                                >
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
