import { useHttp } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    index,
    store,
} from '@/actions/App/Http/Controllers/ObjetivosMensualesDashboardController';
import BotonAccion from '@/components/boton-accion';
import CargandoContenido from '@/components/cargando-contenido';
import {
    CampoHorasMinutos,
    formatearObjetivo,
    InputConUnidad,
    PASO,
    UNIDAD_EN_CAMPO,
} from '@/components/dashboard/objetivos-modal';
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
import { MOTIVO_SIN_CONEXION, motivoErrorHttp } from '@/lib/motivo-error-http';
import { cn } from '@/lib/utils';
import type {
    ConceptoObjetivo,
    ModuloObjetivo,
    ObjetivosMensualesModulo,
} from '@/types';

type Props = {
    modulo: ModuloObjetivo;
    /** Tipo de equipo al que pertenecen los objetivos. */
    tipoEquipo: { id: number; nombre: string };
    open: boolean;
    onOpenChange: (abierto: boolean) => void;
};

type Valores = Partial<Record<ConceptoObjetivo, string>>;

type Datos = {
    anio: string;
    valores: Valores;
};

type Registro = ObjetivosMensualesModulo['registros'][number];

const MESES_DEL_ANIO = Array.from({ length: 12 }, (_, indice) => indice + 1);

const anioActual = () => String(new Date().getFullYear());

/**
 * Valores del año: los objetivos son iguales todos los meses, así que se toman los del
 * primer mes que tenga alguno.
 */
function valoresDelAnio(
    registro: Registro | undefined,
): Partial<Record<ConceptoObjetivo, number>> {
    return Object.values(registro?.meses ?? {}).find(Boolean) ?? {};
}

/**
 * Objetivos de desempeño de un tipo de equipo: por año, la cantidad de mantenimientos y
 * calibraciones por mes y su tiempo promedio (vacío para no tener objetivo). El valor es el
 * mismo todos los meses del año: se guarda en cada uno, para que el Dashboard los compare
 * mes a mes. El histórico lista los años que ya tienen objetivos.
 */
export default function ObjetivosMensualesModal({
    modulo,
    tipoEquipo,
    open,
    onOpenChange,
}: Props) {
    const [objetivos, setObjetivos] = useState<ObjetivosMensualesModulo | null>(
        null,
    );
    const consulta = useHttp<Record<string, never>, ObjetivosMensualesModulo>();
    const formulario = useHttp<Datos, ObjetivosMensualesModulo>({
        anio: anioActual(),
        valores: {},
    });

    // Solo los años de este tipo de equipo, del más reciente al más antiguo.
    const registros = (objetivos?.registros ?? []).filter(
        (registro) => registro.tipo_equipo_id === tipoEquipo.id,
    );

    /** Llena el formulario con lo que ya tiene el año. */
    const elegirAnio = (anio: string, datos = objetivos) => {
        const guardado = datos?.registros.find(
            (registro) =>
                String(registro.anio) === anio &&
                registro.tipo_equipo_id === tipoEquipo.id,
        );

        formulario.clearErrors();
        formulario.setData({
            anio,
            valores: Object.fromEntries(
                Object.entries(valoresDelAnio(guardado)).map(
                    ([concepto, valor]) => [concepto, String(valor)],
                ),
            ),
        });
    };

    // Al abrir se cargan los objetivos.
    useEffect(() => {
        if (!open) {
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
        // Solo al abrir o cambiar de tipo de equipo.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tipoEquipo.id]);

    const cambiarValor = (concepto: ConceptoObjetivo, valor: string) =>
        formulario.setData('valores', {
            ...formulario.data.valores,
            [concepto]: valor,
        });

    const alGuardar = (datos: ObjetivosMensualesModulo, mensaje: string) => {
        setObjetivos(datos);
        toast.success(mensaje);
    };

    const guardar = () => {
        formulario.transform((datos) => {
            const valores = Object.fromEntries(
                (objetivos?.conceptos ?? []).map(({ concepto }) => {
                    const valor = datos.valores[concepto]?.trim() ?? '';

                    return [concepto, valor === '' ? null : Number(valor)];
                }),
            );

            // El mismo objetivo en los 12 meses del año.
            return {
                anio: Number(datos.anio),
                tipo_equipo_id: tipoEquipo.id,
                meses: Object.fromEntries(
                    MESES_DEL_ANIO.map((mes) => [mes, valores]),
                ),
            };
        });

        void formulario.put(store.url(modulo), {
            onSuccess: (datos) =>
                alGuardar(
                    datos,
                    `Objetivos de ${tipoEquipo.nombre} ${formulario.data.anio} guardados.`,
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

    const quitar = (anio: number) => {
        void consulta
            .delete(destroy.url({ modulo, anio, tipoEquipo: tipoEquipo.id }))
            .then((datos) => {
                alGuardar(
                    datos,
                    `Objetivos de ${tipoEquipo.nombre} ${anio} quitados.`,
                );

                if (formulario.data.anio === String(anio)) {
                    elegirAnio(String(anio), datos);
                }
            })
            .catch(() => toast.error('No se pudieron quitar los objetivos.'));
    };

    // Los errores llegan por mes (todos iguales): se muestra el de enero.
    const errorDe = (concepto: ConceptoObjetivo) =>
        (formulario.errors as Record<string, string | undefined>)[
            `meses.1.${concepto}`
        ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        Objetivos de desempeño · {tipoEquipo.nombre}
                    </DialogTitle>
                    <DialogDescription>
                        Lo que se espera cada mes de los equipos de este tipo.
                        El mismo objetivo aplica a todos los meses del año; deja
                        un campo vacío para no tener objetivo.
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
                        <div className="grid gap-2">
                            <Label htmlFor="objetivos-tipo-anio">Año</Label>
                            <Input
                                id="objetivos-tipo-anio"
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

                        {/* Mantenimiento en una fila y calibración en la otra: cantidad | tiempo. */}
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
                                            message={errorDe(concepto)}
                                        />
                                    </div>
                                ),
                            )}
                        </div>

                        {/* Histórico: los años de este tipo de equipo que ya tienen objetivos. */}
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                Histórico de objetivos
                            </p>
                            <div className="max-h-56 overflow-auto rounded-lg border">
                                <table
                                    className="w-full text-sm"
                                    data-test="objetivos-tipo-historico"
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
                                        {registros.map((registro) => {
                                            const valores =
                                                valoresDelAnio(registro);

                                            return (
                                                <tr
                                                    key={registro.anio}
                                                    className={cn(
                                                        String(
                                                            registro.anio,
                                                        ) ===
                                                            formulario.data
                                                                .anio &&
                                                            'bg-primary/10',
                                                    )}
                                                >
                                                    <td className="px-3 py-2 font-medium tabular-nums">
                                                        {registro.anio}
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
                                                            etiqueta={`Editar ${registro.anio}`}
                                                            onClick={() =>
                                                                elegirAnio(
                                                                    String(
                                                                        registro.anio,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <BotonAccion
                                                            icono={Trash2}
                                                            etiqueta={`Quitar ${registro.anio}`}
                                                            onClick={() =>
                                                                quitar(
                                                                    registro.anio,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {registros.length === 0 ? (
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
                                data-test="objetivos-tipo-guardar"
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
