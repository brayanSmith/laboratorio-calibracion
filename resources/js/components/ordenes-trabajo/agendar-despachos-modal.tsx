import { Form, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    despachosListos,
    storeDespacho,
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
import type { DespachoListoItem, IngresoOption } from '@/types';

type Props = {
    tecnicos: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function toComboboxOptions(options: IngresoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/**
 * Revisa los despachos creados al finalizar una calibración (ver
 * CalibracionController::finalizar()) que todavía no tienen técnico de entrega, y
 * permite autorizar la entrega de cada uno y elegir quién la hace (por defecto no está
 * autorizada, y el técnico solo se pide una vez marcada). Solo se agenda el despacho al
 * que se le elige un técnico; el resto queda igual para revisarlo después, la próxima
 * vez que se abra esta modal.
 */
export default function AgendarDespachosModal({
    tecnicos,
    open,
    onOpenChange,
}: Props) {
    const {
        get,
        response,
        processing: cargando,
    } = useHttp<Record<string, never>, DespachoListoItem[]>();

    useEffect(() => {
        if (open) {
            void get(despachosListos().url);
        }
        // Solo se vuelve a cargar cada vez que la modal se abre.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const despachos = response ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <Form
                    {...storeDespacho.form()}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Agendar despachos</DialogTitle>
                                <DialogDescription>
                                    Marca si la entrega de cada equipo queda
                                    autorizada y elige quién la hace. Uno sin
                                    técnico elegido se deja igual para revisarlo
                                    después.
                                </DialogDescription>
                            </DialogHeader>

                            {!cargando && despachos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay despachos pendientes de agendar.
                                </p>
                            ) : (
                                <ul
                                    className="divide-y rounded-lg border bg-background"
                                    data-test="despachos-listos-list"
                                >
                                    {despachos.map((item) => (
                                        <DespachoListoItemRow
                                            key={item.id}
                                            item={item}
                                            tecnicos={tecnicos}
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
                                        processing || despachos.length === 0
                                    }
                                    data-test="agendar-despachos-submit"
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

type DespachoListoItemRowProps = {
    item: DespachoListoItem;
    tecnicos: IngresoOption[];
    errors: Partial<Record<string, string>>;
};

function DespachoListoItemRow({
    item,
    tecnicos,
    errors,
}: DespachoListoItemRowProps) {
    const [tecnicoId, setTecnicoId] = useState('');
    const [entregaAutorizada, setEntregaAutorizada] = useState(
        item.entrega_autorizada,
    );

    const prefijo = `despachos_listos.${item.id}`;

    function alCambiarEntregaAutorizada(checked: boolean) {
        setEntregaAutorizada(checked);

        if (!checked) {
            setTecnicoId('');
        }
    }

    return (
        <li className="space-y-2 px-3 py-2" data-test="despacho-listo-item">
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

            <InputError message={errors[`${prefijo}.id`]} />

            <div className="grid gap-2 rounded-md bg-muted/40 p-2">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`entrega-autorizada-${item.id}`}
                        checked={entregaAutorizada}
                        onCheckedChange={(checked) =>
                            alCambiarEntregaAutorizada(checked === true)
                        }
                        data-test="entrega-autorizada-checkbox"
                    />
                    <Label
                        htmlFor={`entrega-autorizada-${item.id}`}
                        className="text-sm font-normal"
                    >
                        Entrega autorizada
                    </Label>
                </div>

                {entregaAutorizada ? (
                    <>
                        <Label
                            htmlFor={`tecnico-${item.id}`}
                            className="text-xs"
                        >
                            Técnico que entrega
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
                    </>
                ) : null}
            </div>

            <input
                type="hidden"
                name={`despachos_listos[${item.id}][tecnico_id]`}
                value={tecnicoId}
            />
            <input
                type="hidden"
                name={`despachos_listos[${item.id}][entrega_autorizada]`}
                value={entregaAutorizada ? '1' : '0'}
            />
        </li>
    );
}
