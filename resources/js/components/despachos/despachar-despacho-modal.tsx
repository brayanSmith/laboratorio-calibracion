import { Form } from '@inertiajs/react';
import { useState } from 'react';
import DespachoController from '@/actions/App/Modules/Despachos/Http/Controllers/DespachoController';
import Combobox from '@/components/combobox';
import FirmaCanvas from '@/components/firma-canvas';
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
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Despacho, DespachoOption, DespachoOptions } from '@/types';

type Props = {
    despacho: Despacho | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
} & Pick<DespachoOptions, 'clientes' | 'novedadesDespacho'>;

function toComboboxOptions(options: DespachoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/**
 * Registra la entrega de un equipo ya autorizado: muestra los datos del equipo y quién lo
 * entrega, y un toggle rojo/verde de "Entrega recibida". Solo cuando queda en verde pide
 * el cliente que recibe, su firma y, si hubo, la novedad.
 */
export default function DespacharDespachoModal({
    despacho,
    clientes,
    novedadesDespacho,
    open,
    onOpenChange,
}: Props) {
    if (!despacho) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                data-test="despachar-modal"
            >
                <DespacharFormulario
                    key={`${despacho.id}-${String(open)}`}
                    despacho={despacho}
                    clientes={clientes}
                    novedadesDespacho={novedadesDespacho}
                    onSuccess={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

type FormularioProps = {
    despacho: Despacho;
    onSuccess: () => void;
} & Pick<DespachoOptions, 'clientes' | 'novedadesDespacho'>;

function DespacharFormulario({
    despacho,
    clientes,
    novedadesDespacho,
    onSuccess,
}: FormularioProps) {
    const [entregaRecibida, setEntregaRecibida] = useState(false);
    const [clienteRecibeId, setClienteRecibeId] = useState(
        despacho.cliente_recibe_id?.toString() ?? '',
    );
    const [novedadId, setNovedadId] = useState(
        despacho.novedad_id?.toString() ?? '',
    );

    return (
        <Form
            {...DespachoController.update.form(despacho.id)}
            className="min-w-0 space-y-6"
            onSuccess={onSuccess}
        >
            {({ errors, processing }) => (
                <>
                    <DialogHeader>
                        <DialogTitle>Despachar equipo</DialogTitle>
                        <DialogDescription>
                            Registra la entrega del equipo al cliente
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2 rounded-md border p-3">
                        <p className="text-sm font-medium">
                            {despacho.equipo.codigo} ·{' '}
                            {despacho.equipo.tipo_equipo.nombre} ·{' '}
                            {despacho.equipo.modelo}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {despacho.equipo.cliente.nombre}
                        </p>
                        <p className="text-xs">
                            <span className="text-muted-foreground">
                                Orden de trabajo:
                            </span>{' '}
                            {despacho.orden_trabajo_codigo}
                        </p>
                    </div>

                    <div className="grid gap-1">
                        <Label className="text-xs text-muted-foreground">
                            Técnico que entrega
                        </Label>
                        <p className="text-sm">
                            {despacho.tecnico_entrega_nombre ?? 'Sin asignar'}
                        </p>
                    </div>

                    <div className="flex items-center justify-between gap-3 rounded-md border p-3">
                        <Label htmlFor="entrega_recibida_toggle">
                            Entrega recibida
                        </Label>
                        <Switch
                            id="entrega_recibida_toggle"
                            checked={entregaRecibida}
                            onCheckedChange={setEntregaRecibida}
                            aria-label="Entrega recibida"
                            className={
                                entregaRecibida
                                    ? '!bg-emerald-500'
                                    : '!bg-red-500'
                            }
                            data-test="entrega-recibida-toggle"
                        />
                    </div>
                    <input
                        type="hidden"
                        name="entrega_recibida"
                        value={entregaRecibida ? '1' : '0'}
                    />
                    <InputError message={errors.entrega_recibida} />

                    {entregaRecibida ? (
                        <div className="space-y-6" data-test="datos-recepcion">
                            <div className="grid gap-2">
                                <Label htmlFor="fecha_despacho">
                                    Fecha de despacho
                                </Label>
                                <Input
                                    id="fecha_despacho"
                                    type="date"
                                    value={
                                        despacho.fecha_despacho ??
                                        new Date().toLocaleDateString('en-CA')
                                    }
                                    readOnly
                                    disabled
                                    className="sm:max-w-xs"
                                    data-test="despacho-fecha-entrega-input"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Se registra automáticamente el día en que se
                                    despacha el equipo.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cliente_recibe_id">
                                    Cliente que recibe
                                </Label>
                                <Combobox
                                    id="cliente_recibe_id"
                                    name="cliente_recibe_id"
                                    value={clienteRecibeId}
                                    onValueChange={setClienteRecibeId}
                                    options={toComboboxOptions(clientes)}
                                    placeholder="Selecciona un cliente"
                                    searchPlaceholder="Buscar cliente..."
                                />
                                <InputError
                                    message={errors.cliente_recibe_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>Firma del cliente</Label>
                                <Tabs defaultValue="dibujar">
                                    <TabsList>
                                        <TabsTrigger value="dibujar">
                                            Dibujar firma
                                        </TabsTrigger>
                                        <TabsTrigger value="subir">
                                            Subir imagen
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="dibujar">
                                        <FirmaCanvas name="firma_cliente_recibe" />
                                    </TabsContent>

                                    <TabsContent
                                        value="subir"
                                        className="space-y-2"
                                    >
                                        <Input
                                            id="firma_cliente_recibe"
                                            name="firma_cliente_recibe"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Imagen PNG, JPG o WebP de máximo 2
                                            MB.
                                        </p>
                                    </TabsContent>
                                </Tabs>
                                <InputError
                                    message={errors.firma_cliente_recibe}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="novedad_id">
                                    Novedad (opcional)
                                </Label>
                                <Combobox
                                    id="novedad_id"
                                    name="novedad_id"
                                    value={novedadId}
                                    onValueChange={setNovedadId}
                                    options={toComboboxOptions(
                                        novedadesDespacho,
                                    )}
                                    placeholder="Selecciona una novedad"
                                    searchPlaceholder="Buscar novedad..."
                                />
                                <InputError message={errors.novedad_id} />
                            </div>
                        </div>
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            loading={processing}
                            data-test="despachar-submit"
                        >
                            Guardar despacho
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
