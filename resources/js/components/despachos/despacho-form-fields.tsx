import { useState } from 'react';
import Combobox from '@/components/combobox';
import FirmaCanvas from '@/components/firma-canvas';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Despacho, DespachoOption } from '@/types';

type Props = {
    despacho: Despacho;
    tecnicos: DespachoOption[];
    clientes: DespachoOption[];
    novedadesDespacho: DespachoOption[];
    errors: Partial<Record<string, string>>;
};

function toComboboxOptions(options: DespachoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

export default function DespachoFormFields({
    despacho,
    tecnicos,
    clientes,
    novedadesDespacho,
    errors,
}: Props) {
    const [tecnicoEntregaId, setTecnicoEntregaId] = useState(
        despacho.tecnico_entrega_id?.toString() ?? '',
    );
    const [clienteRecibeId, setClienteRecibeId] = useState(
        despacho.cliente_recibe_id?.toString() ?? '',
    );
    const [novedadId, setNovedadId] = useState(
        despacho.novedad_id?.toString() ?? '',
    );

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="space-y-2 rounded-md border p-3 sm:col-span-2">
                <p className="text-sm font-medium">
                    {despacho.equipo.codigo} ·{' '}
                    {despacho.equipo.tipo_equipo.nombre} ·{' '}
                    {despacho.equipo.modelo}
                </p>
                <p className="text-xs text-muted-foreground">
                    {despacho.equipo.cliente.nombre}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tecnico_entrega_id">
                    Técnico que entrega (opcional)
                </Label>
                <Combobox
                    id="tecnico_entrega_id"
                    name="tecnico_entrega_id"
                    value={tecnicoEntregaId}
                    onValueChange={setTecnicoEntregaId}
                    options={toComboboxOptions(tecnicos)}
                    placeholder="Selecciona un técnico"
                    searchPlaceholder="Buscar técnico..."
                />
                <InputError message={errors.tecnico_entrega_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cliente_recibe_id">
                    Cliente que recibe (opcional)
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
                <InputError message={errors.cliente_recibe_id} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="entrega_autorizada"
                    name="entrega_autorizada"
                    defaultChecked={despacho.entrega_autorizada}
                />
                <Label htmlFor="entrega_autorizada" className="font-normal">
                    Entrega autorizada
                </Label>
                <InputError message={errors.entrega_autorizada} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="entrega_recibida"
                    name="entrega_recibida"
                    defaultChecked={despacho.entrega_recibida}
                />
                <Label htmlFor="entrega_recibida" className="font-normal">
                    Entrega recibida
                </Label>
                <InputError message={errors.entrega_recibida} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label>Firma del cliente</Label>

                {despacho.firma_url ? (
                    <img
                        src={despacho.firma_url}
                        alt="Firma actual del cliente"
                        className="h-16 w-fit rounded-md border bg-white object-contain p-1"
                        data-test="firma-preview"
                    />
                ) : null}

                <Tabs defaultValue="dibujar">
                    <TabsList>
                        <TabsTrigger value="dibujar">Dibujar firma</TabsTrigger>
                        <TabsTrigger value="subir">Subir imagen</TabsTrigger>
                    </TabsList>

                    <TabsContent value="dibujar">
                        <FirmaCanvas name="firma_cliente_recibe" />
                    </TabsContent>

                    <TabsContent value="subir" className="space-y-2">
                        <Input
                            id="firma_cliente_recibe"
                            name="firma_cliente_recibe"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                        />
                        <p className="text-xs text-muted-foreground">
                            Imagen PNG, JPG o WebP de máximo 2 MB.
                            {despacho.firma_url
                                ? ' Si eliges una nueva, reemplaza a la actual.'
                                : ''}
                        </p>
                    </TabsContent>
                </Tabs>
                <InputError message={errors.firma_cliente_recibe} />

                {despacho.firma_url ? (
                    <div className="flex items-center gap-3">
                        <Checkbox id="eliminar_firma" name="eliminar_firma" />
                        <Label htmlFor="eliminar_firma">
                            Quitar firma actual
                        </Label>
                    </div>
                ) : null}
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="novedad_id">Novedad (opcional)</Label>
                <Combobox
                    id="novedad_id"
                    name="novedad_id"
                    value={novedadId}
                    onValueChange={setNovedadId}
                    options={toComboboxOptions(novedadesDespacho)}
                    placeholder="Selecciona una novedad"
                    searchPlaceholder="Buscar novedad..."
                />
                <InputError message={errors.novedad_id} />
            </div>
        </div>
    );
}
