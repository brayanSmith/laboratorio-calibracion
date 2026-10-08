import { Form, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    equiposListos,
    store,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import type { EquipoListoParaMantenimientoItem, IngresoOption } from '@/types';

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
 * Revisa los equipos ya recibidos (ingresados) que aún no tienen una orden de trabajo,
 * y permite marcar cuáles quedan listos para mantenimiento y si ese mantenimiento se
 * asigna a un tercero. Solo se crea una orden de trabajo por cada equipo marcado como
 * listo; el resto queda igual para revisarlo después, la próxima vez que se abra esta
 * modal.
 */
export default function AgendarMantenimientoModal({
    tecnicos,
    empresasTerceras,
    open,
    onOpenChange,
}: Props) {
    const {
        get,
        response,
        processing: cargando,
    } = useHttp<Record<string, never>, EquipoListoParaMantenimientoItem[]>();

    useEffect(() => {
        if (open) {
            void get(equiposListos().url);
        }
        // Solo se vuelve a cargar cada vez que la modal se abre.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const equipos = response ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <Form
                    {...store.form()}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Agendar mantenimiento</DialogTitle>
                                <DialogDescription>
                                    Marca los equipos recibidos que quedan
                                    listos para mantenimiento.
                                </DialogDescription>
                            </DialogHeader>

                            {!cargando && equipos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay equipos recibidos pendientes de
                                    agendar mantenimiento.
                                </p>
                            ) : (
                                <ul
                                    className="divide-y rounded-lg border bg-background"
                                    data-test="equipos-listos-list"
                                >
                                    {equipos.map((item) => (
                                        <EquipoListoItem
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
                                        processing || equipos.length === 0
                                    }
                                    data-test="agendar-mantenimiento-submit"
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

type EquipoListoItemProps = {
    item: EquipoListoParaMantenimientoItem;
    tecnicos: IngresoOption[];
    empresasTerceras: IngresoOption[];
    errors: Partial<Record<string, string>>;
};

function EquipoListoItem({
    item,
    tecnicos,
    empresasTerceras,
    errors,
}: EquipoListoItemProps) {
    const [listoParaMantenimiento, setListoParaMantenimiento] = useState(false);
    const [mantenimientoAsignadoTercero, setMantenimientoAsignadoTercero] =
        useState(false);
    const [tecnicoId, setTecnicoId] = useState('');
    const [empresaTerceroId, setEmpresaTerceroId] = useState('');

    const prefijo = `equipos_listos.${item.id}`;

    return (
        <li className="space-y-2 px-3 py-2" data-test="equipo-listo-item">
            <div>
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">
                        {item.equipo.codigo} · {item.equipo.tipo_equipo.nombre}{' '}
                        · {item.equipo.modelo}
                    </span>
                    {item.devolucion ? (
                        <Badge
                            className="!border-transparent !bg-amber-500 !text-white"
                            data-test="equipo-devolucion-badge"
                        >
                            Por devolución
                        </Badge>
                    ) : null}
                </div>
                {item.equipo.cliente ? (
                    <p className="text-xs text-muted-foreground">
                        {item.equipo.cliente.nombre}
                    </p>
                ) : null}
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={`listo-para-mantenimiento-${item.id}`}
                    checked={listoParaMantenimiento}
                    onCheckedChange={(checked) => {
                        const marcado = checked === true;

                        setListoParaMantenimiento(marcado);

                        if (!marcado) {
                            setMantenimientoAsignadoTercero(false);
                        }
                    }}
                    data-test="listo-para-mantenimiento-checkbox"
                />
                <Label
                    htmlFor={`listo-para-mantenimiento-${item.id}`}
                    className="text-sm font-normal"
                >
                    Listo para mantenimiento
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={`mantenimiento-asignado-tercero-${item.id}`}
                    checked={mantenimientoAsignadoTercero}
                    disabled={!listoParaMantenimiento}
                    onCheckedChange={(checked) =>
                        setMantenimientoAsignadoTercero(checked === true)
                    }
                    data-test="mantenimiento-asignado-tercero-checkbox"
                />
                <Label
                    htmlFor={`mantenimiento-asignado-tercero-${item.id}`}
                    className="text-sm font-normal"
                >
                    Mantenimiento asignado a tercero
                </Label>
            </div>

            <InputError message={errors[`${prefijo}.id`]} />

            {listoParaMantenimiento && !mantenimientoAsignadoTercero ? (
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

            {listoParaMantenimiento && mantenimientoAsignadoTercero ? (
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

            {listoParaMantenimiento ? (
                <>
                    <input
                        type="hidden"
                        name={`equipos_listos[${item.id}][listo_para_mantenimiento]`}
                        value="1"
                    />
                    <input
                        type="hidden"
                        name={`equipos_listos[${item.id}][mantenimiento_asignado_tercero]`}
                        value={mantenimientoAsignadoTercero ? '1' : '0'}
                    />
                    {mantenimientoAsignadoTercero ? (
                        <input
                            type="hidden"
                            name={`equipos_listos[${item.id}][empresa_tercero_id]`}
                            value={empresaTerceroId}
                        />
                    ) : (
                        <input
                            type="hidden"
                            name={`equipos_listos[${item.id}][tecnico_id]`}
                            value={tecnicoId}
                        />
                    )}
                </>
            ) : null}
        </li>
    );
}
