import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MESES, TIPOS_OBJETIVO_USUARIO } from '@/components/objetivos/tipos';
import type {
    ObjetivoUsuario,
    Opcion,
    TipoObjetivoUsuario,
} from '@/components/objetivos/tipos';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    esPersonalizado,
    replicarHaciaAdelante,
    totalDelAnio,
} from '@/lib/objetivos-usuario';
import { store } from '@/routes/objetivos';

type Props = {
    /** Objetivos a editar; null para crear los de un usuario nuevo. */
    objetivo: ObjetivoUsuario | null;
    usuarios: Opcion[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type Datos = {
    user_id: string;
    anio: string;
    /** Por tipo, un valor por mes, de enero (posición 0) a diciembre. */
    meses: Record<TipoObjetivoUsuario, string[]>;
};

type Errores = Partial<Record<string, string>>;

const formatoTotal = new Intl.NumberFormat('es', { maximumFractionDigits: 2 });

const mesesIguales = (valor: string): string[] => MESES.map(() => valor);

function mesesDelTipo(
    objetivo: ObjetivoUsuario | null,
    tipo: TipoObjetivoUsuario,
): string[] {
    const delTipo = objetivo?.tipos[tipo];

    return MESES.map((_, indice) => {
        const valor = delTipo?.meses[indice + 1];

        return valor === undefined ? '' : String(valor);
    });
}

/**
 * Crear o editar los objetivos de un usuario en un año: los de mantenimientos y los de
 * calibraciones, cada uno con un valor para todos los meses o uno por mes.
 */
export default function ObjetivoUsuarioModal({
    objetivo,
    usuarios,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                {open ? (
                    <Formulario
                        key={objetivo?.clave ?? 'nuevo'}
                        objetivo={objetivo}
                        usuarios={usuarios}
                        onGuardado={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Formulario({
    objetivo,
    usuarios,
    onGuardado,
}: {
    objetivo: ObjetivoUsuario | null;
    usuarios: Opcion[];
    onGuardado: () => void;
}) {
    const form = useForm<Datos>({
        user_id: objetivo ? String(objetivo.user_id) : '',
        anio: String(objetivo?.anio ?? new Date().getFullYear()),
        meses: {
            MANTENIMIENTO: mesesDelTipo(objetivo, 'MANTENIMIENTO'),
            CALIBRACION: mesesDelTipo(objetivo, 'CALIBRACION'),
        },
    });
    const editando = objetivo !== null;
    const errores = form.errors as Errores;

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();

        form.transform((datos) => ({
            ...datos,
            // El servidor espera, por tipo, los meses del 1 al 12.
            meses: Object.fromEntries(
                Object.entries(datos.meses).map(([tipo, valores]) => [
                    tipo,
                    Object.fromEntries(
                        valores.map((valor, indice) => [indice + 1, valor]),
                    ),
                ]),
            ),
        }));
        form.put(store.url(), { preserveScroll: true, onSuccess: onGuardado });
    };

    return (
        <form onSubmit={enviar} className="space-y-6">
            <DialogHeader>
                <DialogTitle>
                    {editando ? 'Editar objetivos' : 'Nuevos objetivos'}
                </DialogTitle>
                <DialogDescription>
                    El objetivo mensual vale para todos los meses del año. Marca
                    Personalizado para poner uno distinto en cada mes: lo que
                    escribas en un mes se copia a los siguientes.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="user_id">Usuario</Label>
                    <Select
                        value={form.data.user_id}
                        onValueChange={(valor) =>
                            form.setData('user_id', valor)
                        }
                        disabled={editando}
                    >
                        <SelectTrigger id="user_id" className="w-full">
                            <SelectValue placeholder="Selecciona un usuario" />
                        </SelectTrigger>
                        <SelectContent>
                            {usuarios.map((usuario) => (
                                <SelectItem
                                    key={usuario.id}
                                    value={String(usuario.id)}
                                >
                                    {usuario.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errores.user_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="anio">Año</Label>
                    <Input
                        id="anio"
                        type="number"
                        min={2000}
                        max={2100}
                        value={form.data.anio}
                        onChange={(evento) =>
                            form.setData('anio', evento.target.value)
                        }
                        disabled={editando}
                        required
                    />
                    <InputError message={errores.anio} />
                </div>
            </div>

            {TIPOS_OBJETIVO_USUARIO.map((tipo) => (
                <SeccionTipo
                    key={tipo.value}
                    tipo={tipo.value}
                    titulo={tipo.label}
                    valores={form.data.meses[tipo.value]}
                    errores={errores}
                    onChange={(valores) =>
                        form.setData('meses', {
                            ...form.data.meses,
                            [tipo.value]: valores,
                        })
                    }
                />
            ))}

            <InputError message={errores.meses} />

            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button type="button" variant="secondary">
                        Cancelar
                    </Button>
                </DialogClose>

                <Button
                    type="submit"
                    loading={form.processing}
                    data-test="objetivo-usuario-submit"
                >
                    Guardar objetivos
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * Objetivos de un tipo: un solo valor para los 12 meses o, marcando Personalizado, uno
 * por mes. Cada tipo se personaliza por separado.
 */
function SeccionTipo({
    tipo,
    titulo,
    valores,
    errores,
    onChange,
}: {
    tipo: TipoObjetivoUsuario;
    titulo: string;
    valores: string[];
    errores: Errores;
    onChange: (valores: string[]) => void;
}) {
    const [personalizado, setPersonalizado] = useState(() =>
        esPersonalizado(valores),
    );
    const total = totalDelAnio(valores);
    const errorDeMes = (indice: number) =>
        errores[`meses.${tipo}.${indice + 1}`];
    const primerErrorDeMes = MESES.map((_, indice) => errorDeMes(indice)).find(
        (error) => error !== undefined,
    );
    const id = tipo.toLowerCase();

    return (
        <fieldset
            className="grid gap-3 rounded-lg border p-4"
            data-test={`objetivos-${id}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <legend className="font-medium">{titulo}</legend>
                <span className="text-sm text-muted-foreground">
                    Total del año:{' '}
                    <strong className="text-foreground">
                        {total === null ? '—' : formatoTotal.format(total)}
                    </strong>
                </span>
            </div>

            <div className="flex flex-wrap items-end gap-4">
                {!personalizado ? (
                    <div className="grid gap-2">
                        <Label htmlFor={`${id}-mensual`}>
                            Objetivo mensual
                        </Label>
                        <Input
                            id={`${id}-mensual`}
                            type="number"
                            min={0}
                            step="1"
                            className="w-40"
                            value={valores[0]}
                            onChange={(evento) =>
                                onChange(mesesIguales(evento.target.value))
                            }
                            data-test={`objetivo-${id}-mensual`}
                        />
                    </div>
                ) : null}

                <div className="flex h-9 items-center gap-2">
                    <Checkbox
                        id={`${id}-personalizado`}
                        checked={personalizado}
                        onCheckedChange={(marcado) => {
                            setPersonalizado(marcado === true);

                            // Al volver a un solo objetivo, todos los meses toman el de enero.
                            if (marcado !== true) {
                                onChange(mesesIguales(valores[0]));
                            }
                        }}
                        data-test={`objetivo-${id}-personalizado`}
                    />
                    <Label htmlFor={`${id}-personalizado`}>Personalizado</Label>
                </div>
            </div>

            {personalizado ? (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {MESES.map((mes, indice) => (
                        <div key={mes} className="grid gap-1">
                            <Label
                                htmlFor={`${id}-mes-${indice + 1}`}
                                className="text-xs text-muted-foreground"
                            >
                                {mes}
                            </Label>
                            <Input
                                id={`${id}-mes-${indice + 1}`}
                                type="number"
                                min={0}
                                step="1"
                                value={valores[indice]}
                                onChange={(evento) =>
                                    onChange(
                                        replicarHaciaAdelante(
                                            valores,
                                            indice,
                                            evento.target.value,
                                        ),
                                    )
                                }
                                data-test={`objetivo-${id}-mes-${indice + 1}`}
                            />
                            <InputError message={errorDeMes(indice)} />
                        </div>
                    ))}
                </div>
            ) : (
                <InputError message={primerErrorDeMes} />
            )}
        </fieldset>
    );
}
