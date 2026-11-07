import { useHttp } from '@inertiajs/react';
import { Pencil, Target, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    index,
    store,
} from '@/actions/App/Http/Controllers/ObjetivosDashboardController';
import BotonAccion from '@/components/boton-accion';
import CargandoContenido from '@/components/cargando-contenido';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatearCantidad,
    formatearDias,
    formatearHoras,
    formatearPorcentaje,
} from '@/lib/indicadores';
import { separarHoras, unirHoras } from '@/lib/objetivos';
import type { FormatoObjetivo } from '@/lib/objetivos';
import { MOTIVO_SIN_CONEXION, motivoErrorHttp } from '@/lib/motivo-error-http';
import { cn } from '@/lib/utils';
import type {
    ConceptoObjetivo,
    ModuloObjetivo,
    ObjetivosModulo,
} from '@/types';

type Props = {
    modulo: ModuloObjetivo;
    /** Nombre del módulo, para el título del formulario. */
    titulo: string;
    /** Se llama al guardar o quitar objetivos, para volver a pedir los datos del módulo. */
    onCambio: () => void;
};

type Datos = {
    anio: string;
    valores: Partial<Record<ConceptoObjetivo, string>>;
};

const anioActual = () => String(new Date().getFullYear());

/** Incremento de cada campo: cantidades enteras; días y porcentaje con un decimal. */
const PASO: Record<Exclude<FormatoObjetivo, 'horas'>, string> = {
    cantidad: '1',
    dias: '0.1',
    porcentaje: '0.1',
};

/** Un objetivo escrito como se lee: "1 h 30 min", "3,5 días", "95 %", "480". */
function formatearObjetivo(valor: number, formato: FormatoObjetivo): string {
    switch (formato) {
        case 'horas':
            return formatearHoras(valor);
        case 'dias':
            return formatearDias(valor);
        case 'porcentaje':
            return formatearPorcentaje(valor);
        case 'cantidad':
            return formatearCantidad(valor);
    }
}

/** Unidad que se ve dentro del campo, a la derecha (las cantidades no la llevan). */
const UNIDAD_EN_CAMPO: Partial<Record<FormatoObjetivo, string>> = {
    dias: 'días',
    porcentaje: '%',
};

/**
 * Campo numérico con su unidad escrita dentro, a la derecha (ej. "30 min", "95 %"), sin
 * las flechas del navegador para que no la tapen.
 */
function InputConUnidad({
    unidad,
    ancho = 'w-32',
    className,
    ...props
}: ComponentProps<typeof Input> & {
    unidad?: string;
    /** Ancho del campo (clase de Tailwind): son números cortos, no hace falta todo el ancho. */
    ancho?: string;
}) {
    return (
        <div className={cn('relative', ancho)}>
            <Input
                type="number"
                className={cn(
                    '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                    unidad && 'pr-12',
                    className,
                )}
                {...props}
            />
            {unidad ? (
                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                    {unidad}
                </span>
            ) : null}
        </div>
    );
}

/**
 * Tiempo en horas y minutos (ej. 0 h 30 min, 1 h 30 min), en lugar de un número decimal
 * de horas. Se guarda en horas: 1 h 30 min es 1.5.
 */
function CampoHorasMinutos({
    id,
    value,
    onChange,
}: {
    id: string;
    value: string;
    onChange: (valor: string) => void;
}) {
    const { horas, minutos } = separarHoras(value);

    return (
        <div className="flex gap-2" data-test={id}>
            <InputConUnidad
                id={id}
                unidad="h"
                ancho="w-24"
                min={0}
                step={1}
                placeholder="0"
                aria-label="Horas"
                value={horas}
                onChange={(evento) =>
                    onChange(unirHoras(evento.target.value, minutos))
                }
            />
            <InputConUnidad
                unidad="min"
                ancho="w-24"
                min={0}
                max={59}
                step={5}
                placeholder="0"
                aria-label="Minutos"
                value={minutos}
                onChange={(evento) =>
                    onChange(unirHoras(horas, evento.target.value))
                }
            />
        </div>
    );
}

/**
 * Objetivos anuales de un módulo del dashboard: se elige el año y se escribe el objetivo
 * de cada concepto del módulo (vacío para no tener objetivo). Los años que ya tienen
 * objetivos se listan para editarlos o quitarlos.
 */
export default function ObjetivosModal({ modulo, titulo, onCambio }: Props) {
    const [abierto, setAbierto] = useState(false);
    const [objetivos, setObjetivos] = useState<ObjetivosModulo | null>(null);
    const consulta = useHttp<Record<string, never>, ObjetivosModulo>();
    const formulario = useHttp<Datos, ObjetivosModulo>({
        anio: anioActual(),
        valores: {},
    });

    /** Llena el formulario con los objetivos que ya tiene el año. */
    const elegirAnio = (anio: string, datos = objetivos) => {
        const guardados =
            datos?.anios.find((item) => String(item.anio) === anio)?.valores ??
            {};

        formulario.clearErrors();
        formulario.setData({
            anio,
            valores: Object.fromEntries(
                Object.entries(guardados).map(([concepto, valor]) => [
                    concepto,
                    String(valor),
                ]),
            ),
        });
    };

    const cambiarApertura = (valor: boolean) => {
        setAbierto(valor);

        if (!valor) {
            return;
        }

        setObjetivos(null);
        void consulta
            .get(index.url(modulo))
            .then((datos) => {
                setObjetivos(datos);
                elegirAnio(anioActual(), datos);
            })
            .catch(() => toast.error('No se pudieron cargar los objetivos.'));
    };

    const cambiarValor = (concepto: ConceptoObjetivo, valor: string) =>
        formulario.setData('valores', {
            ...formulario.data.valores,
            [concepto]: valor,
        });

    const alGuardar = (datos: ObjetivosModulo, mensaje: string) => {
        setObjetivos(datos);
        toast.success(mensaje);
        onCambio();
    };

    const guardar = () => {
        formulario.transform((datos) => ({
            anio: Number(datos.anio),
            valores: Object.fromEntries(
                (objetivos?.conceptos ?? []).map(({ concepto }) => {
                    const valor = datos.valores[concepto]?.trim() ?? '';

                    return [concepto, valor === '' ? null : Number(valor)];
                }),
            ),
        }));

        void formulario.put(store.url(modulo), {
            onSuccess: (datos) =>
                alGuardar(
                    datos,
                    `Objetivos de ${formulario.data.anio} guardados.`,
                ),
            onHttpException: (respuesta) => {
                // Los errores de validación se muestran en cada campo.
                if (respuesta.status !== 422) {
                    toast.error(
                        `No se pudieron guardar: ${motivoErrorHttp(respuesta)}`,
                    );
                }
            },
            onNetworkError: () => {
                toast.error(`No se pudieron guardar: ${MOTIVO_SIN_CONEXION}`);
            },
        });
    };

    const quitarAnio = (anio: number) => {
        void consulta
            .delete(destroy.url({ modulo, anio }))
            .then((datos) => {
                alGuardar(datos, `Objetivos de ${anio} quitados.`);

                if (formulario.data.anio === String(anio)) {
                    elegirAnio(String(anio), datos);
                }
            })
            .catch(() => toast.error('No se pudieron quitar los objetivos.'));
    };

    return (
        <Dialog open={abierto} onOpenChange={cambiarApertura}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test={`objetivos-${modulo}`}
                >
                    <Target className="h-4 w-4" /> Objetivos
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Objetivos · {titulo}</DialogTitle>
                    <DialogDescription>
                        Define el objetivo esperado de cada año para este
                        módulo. Deja un campo vacío para no tener objetivo en
                        ese concepto.
                    </DialogDescription>
                </DialogHeader>

                {objetivos === null ? (
                    <CargandoContenido mensaje="Cargando objetivos..." />
                ) : (
                    <form
                        className="grid gap-4"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            guardar();
                        }}
                    >
                        {/* Arriba el año; debajo sus objetivos en dos columnas. */}
                        <div className="grid gap-2">
                            <Label htmlFor={`objetivos-${modulo}-anio`}>
                                Año
                            </Label>
                            <Input
                                id={`objetivos-${modulo}-anio`}
                                type="number"
                                min={2000}
                                max={2100}
                                value={formulario.data.anio}
                                onChange={(evento) =>
                                    elegirAnio(evento.target.value)
                                }
                                className="w-32"
                            />
                            <InputError message={formulario.errors.anio} />
                        </div>

                        <div className="grid items-start gap-4 rounded-lg border p-4 sm:grid-cols-2">
                            {objetivos.conceptos.map(
                                ({
                                    concepto,
                                    label,
                                    unidad,
                                    formato,
                                    maximo,
                                }) => (
                                    <div key={concepto} className="grid gap-2">
                                        <Label
                                            htmlFor={`objetivo-${concepto}`}
                                            className="leading-snug"
                                        >
                                            <span>
                                                {label}
                                                {/* Las demás unidades van dentro del campo. */}
                                                {formato === 'cantidad' ? (
                                                    <span className="font-normal text-muted-foreground">
                                                        {' '}
                                                        ({unidad})
                                                    </span>
                                                ) : null}
                                            </span>
                                        </Label>
                                        {formato === 'horas' ? (
                                            <CampoHorasMinutos
                                                id={`objetivo-${concepto}`}
                                                value={
                                                    formulario.data.valores[
                                                        concepto
                                                    ] ?? ''
                                                }
                                                onChange={(valor) =>
                                                    cambiarValor(
                                                        concepto,
                                                        valor,
                                                    )
                                                }
                                            />
                                        ) : (
                                            <InputConUnidad
                                                id={`objetivo-${concepto}`}
                                                unidad={
                                                    UNIDAD_EN_CAMPO[formato]
                                                }
                                                min={0}
                                                max={maximo ?? undefined}
                                                step={PASO[formato]}
                                                placeholder="Sin objetivo"
                                                value={
                                                    formulario.data.valores[
                                                        concepto
                                                    ] ?? ''
                                                }
                                                onChange={(evento) =>
                                                    cambiarValor(
                                                        concepto,
                                                        evento.target.value,
                                                    )
                                                }
                                                data-test={`objetivo-${concepto}`}
                                            />
                                        )}
                                        <InputError
                                            message={
                                                (
                                                    formulario.errors as Record<
                                                        string,
                                                        string | undefined
                                                    >
                                                )[`valores.${concepto}`]
                                            }
                                        />
                                    </div>
                                ),
                            )}
                        </div>

                        {/* Histórico: los objetivos de cada año, del más reciente al más antiguo. */}
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                Histórico de objetivos
                            </p>
                            <div className="max-h-64 overflow-auto rounded-lg border">
                                <table
                                    className="w-full text-sm"
                                    data-test={`objetivos-${modulo}-historico`}
                                >
                                    <thead className="sticky top-0 bg-muted text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Año
                                            </th>
                                            {objetivos.conceptos.map(
                                                ({
                                                    concepto,
                                                    label,
                                                    unidad,
                                                }) => (
                                                    <th
                                                        key={concepto}
                                                        className="px-3 py-2 text-right font-medium"
                                                    >
                                                        {label}
                                                        <span className="block text-xs font-normal">
                                                            {unidad}
                                                        </span>
                                                    </th>
                                                ),
                                            )}
                                            <th className="px-3 py-2">
                                                <span className="sr-only">
                                                    Acciones
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {objetivos.anios.map(
                                            ({ anio, valores }) => (
                                                <tr
                                                    key={anio}
                                                    className={cn(
                                                        String(anio) ===
                                                            formulario.data
                                                                .anio &&
                                                            'bg-primary/10',
                                                    )}
                                                >
                                                    <td className="px-3 py-2 font-medium tabular-nums">
                                                        {anio}
                                                    </td>
                                                    {objetivos.conceptos.map(
                                                        ({
                                                            concepto,
                                                            formato,
                                                        }) => (
                                                            <td
                                                                key={concepto}
                                                                className="px-3 py-2 text-right tabular-nums"
                                                            >
                                                                {valores[
                                                                    concepto
                                                                ] === undefined
                                                                    ? '—'
                                                                    : formatearObjetivo(
                                                                          valores[
                                                                              concepto
                                                                          ] as number,
                                                                          formato,
                                                                      )}
                                                            </td>
                                                        ),
                                                    )}
                                                    <td className="px-3 py-1 text-right whitespace-nowrap">
                                                        <BotonAccion
                                                            icono={Pencil}
                                                            etiqueta={`Editar ${anio}`}
                                                            onClick={() =>
                                                                elegirAnio(
                                                                    String(
                                                                        anio,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <BotonAccion
                                                            icono={Trash2}
                                                            etiqueta={`Quitar ${anio}`}
                                                            onClick={() =>
                                                                quitarAnio(anio)
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                        {objetivos.anios.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        objetivos.conceptos
                                                            .length + 2
                                                    }
                                                    className="px-3 py-4 text-center text-muted-foreground"
                                                >
                                                    Aún no hay objetivos
                                                    registrados.
                                                </td>
                                            </tr>
                                        ) : null}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cerrar
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                loading={formulario.processing}
                                data-test={`objetivos-${modulo}-guardar`}
                            >
                                Guardar objetivos
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
