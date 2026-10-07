import { Form, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    equiposListosCalibracion,
    storeCalibracion,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
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
import { Label } from '@/components/ui/label';
import type { IngresoOption, OrdenListaParaCalibracionItem } from '@/types';

type Props = {
    tecnicos: IngresoOption[];
    empresasTerceras: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function toComboboxOptions(options: IngresoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/**
 * Revisa las órdenes de trabajo con el mantenimiento ya finalizado que todavía no
 * quedan listas para calibración, y permite marcar cuáles lo quedan y si esa
 * calibración se asigna a un tercero. Solo se agenda cada orden marcada; el resto
 * queda igual para revisarla después, la próxima vez que se abra esta modal.
 */
export default function AgendarCalibracionesModal({
    tecnicos,
    empresasTerceras,
    open,
    onOpenChange,
}: Props) {
    const {
        get,
        response,
        processing: cargando,
    } = useHttp<Record<string, never>, OrdenListaParaCalibracionItem[]>();

    useEffect(() => {
        if (open) {
            void get(equiposListosCalibracion().url);
        }
        // Solo se vuelve a cargar cada vez que la modal se abre.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const ordenes = response ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <Form
                    {...storeCalibracion.form()}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Agendar calibraciones</DialogTitle>
                                <DialogDescription>
                                    Marca las órdenes con mantenimiento
                                    finalizado que quedan listas para
                                    calibración.
                                </DialogDescription>
                            </DialogHeader>

                            {!cargando && ordenes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay órdenes de trabajo pendientes de
                                    agendar calibración.
                                </p>
                            ) : (
                                <ul
                                    className="divide-y rounded-lg border bg-background"
                                    data-test="ordenes-listas-calibracion-list"
                                >
                                    {ordenes.map((item) => (
                                        <OrdenListaItem
                                            key={item.id}
                                            item={item}
                                            tecnicos={tecnicos}
                                            empresasTerceras={empresasTerceras}
                                            errors={errors}
                                        />
                                    ))}
                                </ul>
                            )}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing || ordenes.length === 0
                                    }
                                    data-test="agendar-calibraciones-submit"
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

type OrdenListaItemProps = {
    item: OrdenListaParaCalibracionItem;
    tecnicos: IngresoOption[];
    empresasTerceras: IngresoOption[];
    errors: Partial<Record<string, string>>;
};

function OrdenListaItem({
    item,
    tecnicos,
    empresasTerceras,
    errors,
}: OrdenListaItemProps) {
    const [listoParaCalibracion, setListoParaCalibracion] = useState(false);
    const [calibracionAsignadaTercero, setCalibracionAsignadaTercero] =
        useState(false);
    const [tecnicoId, setTecnicoId] = useState('');
    const [empresaTerceroId, setEmpresaTerceroId] = useState('');

    const prefijo = `ordenes_listas.${item.id}`;

    return (
        <li className="space-y-2 px-3 py-2" data-test="orden-lista-item">
            <div>
                <span className="text-sm font-medium">
                    {item.equipo.codigo} · {item.equipo.tipo_equipo.nombre} ·{' '}
                    {item.equipo.modelo}
                </span>
                {item.equipo.cliente ? (
                    <p className="text-xs text-muted-foreground">
                        {item.equipo.cliente.nombre}
                    </p>
                ) : null}
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={`listo-para-calibracion-${item.id}`}
                    checked={listoParaCalibracion}
                    onCheckedChange={(checked) => {
                        const marcado = checked === true;

                        setListoParaCalibracion(marcado);

                        if (!marcado) {
                            setCalibracionAsignadaTercero(false);
                        }
                    }}
                    data-test="listo-para-calibracion-checkbox"
                />
                <Label
                    htmlFor={`listo-para-calibracion-${item.id}`}
                    className="text-sm font-normal"
                >
                    Listo para calibración
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={`calibracion-asignada-tercero-${item.id}`}
                    checked={calibracionAsignadaTercero}
                    disabled={!listoParaCalibracion}
                    onCheckedChange={(checked) =>
                        setCalibracionAsignadaTercero(checked === true)
                    }
                    data-test="calibracion-asignada-tercero-checkbox"
                />
                <Label
                    htmlFor={`calibracion-asignada-tercero-${item.id}`}
                    className="text-sm font-normal"
                >
                    Calibración asignada a tercero
                </Label>
            </div>

            <InputError message={errors[`${prefijo}.id`]} />

            {listoParaCalibracion && !calibracionAsignadaTercero ? (
                <div className="grid gap-2 rounded-md bg-muted/40 p-2">
                    <Label htmlFor={`tecnico-${item.id}`} className="text-xs">
                        Técnico
                    </Label>
                    <Combobox
                        id={`tecnico-${item.id}`}
                        value={tecnicoId}
                        onValueChange={setTecnicoId}
                        options={toComboboxOptions(tecnicos)}
                        placeholder="Selecciona un técnico"
                        searchPlaceholder="Buscar técnico..."
                    />
                    <InputError message={errors[`${prefijo}.tecnico_id`]} />
                </div>
            ) : null}

            {listoParaCalibracion && calibracionAsignadaTercero ? (
                <div className="grid gap-2 rounded-md bg-muted/40 p-2">
                    <Label
                        htmlFor={`empresa-tercero-${item.id}`}
                        className="text-xs"
                    >
                        Empresa tercero
                    </Label>
                    <Combobox
                        id={`empresa-tercero-${item.id}`}
                        value={empresaTerceroId}
                        onValueChange={setEmpresaTerceroId}
                        options={toComboboxOptions(empresasTerceras)}
                        placeholder="Selecciona una empresa"
                        searchPlaceholder="Buscar empresa..."
                    />
                    <InputError
                        message={errors[`${prefijo}.empresa_tercero_id`]}
                    />
                </div>
            ) : null}

            {listoParaCalibracion ? (
                <>
                    <input
                        type="hidden"
                        name={`ordenes_listas[${item.id}][listo_para_calibracion]`}
                        value="1"
                    />
                    <input
                        type="hidden"
                        name={`ordenes_listas[${item.id}][calibracion_asignado_tercero]`}
                        value={calibracionAsignadaTercero ? '1' : '0'}
                    />
                    {calibracionAsignadaTercero ? (
                        <input
                            type="hidden"
                            name={`ordenes_listas[${item.id}][empresa_tercero_id]`}
                            value={empresaTerceroId}
                        />
                    ) : (
                        <input
                            type="hidden"
                            name={`ordenes_listas[${item.id}][tecnico_id]`}
                            value={tecnicoId}
                        />
                    )}
                </>
            ) : null}
        </li>
    );
}
