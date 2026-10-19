import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import ItemController from '@/actions/App/Http/Controllers/ItemController';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Item } from '@/types';

type Entrada = {
    id: number;
    fecha_registro: string | null;
    cantidad: number;
    precio_unitario: number;
    precio_total: number;
    codigo_compra: string | null;
    recibido: boolean;
    fecha_recibido: string | null;
    observaciones: string | null;
};

type Salida = {
    id: number;
    cantidad: number;
    orden_trabajo_codigo: string | null;
    tipo_mantenimiento: string | null;
    estado_mantenimiento: string | null;
    fecha: string | null;
};

type Movimientos = {
    entradas: Entrada[];
    salidas: Salida[];
};

type Props = {
    item: Item | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type ValorOrdenable = string | number | null;

type Columna<T> = {
    id: string;
    titulo: string;
    valor: (fila: T) => ValorOrdenable;
    celda: (fila: T) => ReactNode;
    pie?: ReactNode;
    numerica?: boolean;
};

type Orden = { id: string; descendente: boolean } | null;

const formatoMoneda = (valor: number) =>
    valor.toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

const sumar = <T,>(filas: T[], valor: (fila: T) => number) =>
    filas.reduce((total, fila) => total + valor(fila), 0);

function compararValores(a: ValorOrdenable, b: ValorOrdenable): number {
    if (a === null && b === null) {
        return 0;
    }

    if (a === null) {
        return 1;
    }

    if (b === null) {
        return -1;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return String(a).localeCompare(String(b), 'es', { numeric: true });
}

function TablaOrdenable<T extends { id: number }>({
    filas,
    columnas,
    testId,
}: {
    filas: T[];
    columnas: Columna<T>[];
    testId: string;
}) {
    const [orden, setOrden] = useState<Orden>(null);

    const filasOrdenadas = useMemo(() => {
        const columna = columnas.find((c) => c.id === orden?.id);

        if (!orden || !columna) {
            return filas;
        }

        return [...filas].sort((a, b) => {
            // Los nulos siempre van al final, sin importar la dirección.
            const valorA = columna.valor(a);
            const valorB = columna.valor(b);

            if (valorA === null || valorB === null) {
                return compararValores(valorA, valorB);
            }

            const resultado = compararValores(valorA, valorB);

            return orden.descendente ? -resultado : resultado;
        });
    }, [filas, columnas, orden]);

    const alternarOrden = (id: string) =>
        setOrden((actual) => {
            if (actual?.id !== id) {
                return { id, descendente: false };
            }

            return actual.descendente ? null : { id, descendente: true };
        });

    const hayPie = columnas.some((columna) => columna.pie !== undefined);

    return (
        <div className="max-h-96 overflow-auto rounded-md border">
            <table className="w-full text-sm">
                <thead className="sticky top-0 z-10 bg-muted">
                    <tr>
                        {columnas.map((columna) => {
                            const activa = orden?.id === columna.id;
                            const Icono = !activa
                                ? ChevronsUpDown
                                : orden.descendente
                                  ? ArrowDown
                                  : ArrowUp;

                            return (
                                <th
                                    key={columna.id}
                                    aria-sort={
                                        !activa
                                            ? 'none'
                                            : orden.descendente
                                              ? 'descending'
                                              : 'ascending'
                                    }
                                    className="px-3 py-2 font-medium text-muted-foreground"
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            alternarOrden(columna.id)
                                        }
                                        data-test={`${testId}-orden-${columna.id}`}
                                        className={`inline-flex items-center gap-1 whitespace-nowrap hover:text-foreground ${
                                            columna.numerica
                                                ? 'w-full justify-end'
                                                : ''
                                        }`}
                                    >
                                        {columna.titulo}
                                        <Icono
                                            className={`size-3.5 ${activa ? '' : 'opacity-50'}`}
                                        />
                                    </button>
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {filasOrdenadas.map((fila) => (
                        <tr key={fila.id} data-test={`${testId}-fila`}>
                            {columnas.map((columna) => (
                                <td
                                    key={columna.id}
                                    className={`px-3 py-2 align-top ${
                                        columna.numerica
                                            ? 'text-right tabular-nums'
                                            : ''
                                    }`}
                                >
                                    {columna.celda(fila)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                {hayPie ? (
                    <tfoot
                        className="sticky bottom-0 bg-muted font-medium"
                        data-test={`${testId}-totales`}
                    >
                        <tr>
                            {columnas.map((columna) => (
                                <td
                                    key={columna.id}
                                    className={`px-3 py-2 ${
                                        columna.numerica
                                            ? 'text-right tabular-nums'
                                            : ''
                                    }`}
                                >
                                    {columna.pie ?? null}
                                </td>
                            ))}
                        </tr>
                    </tfoot>
                ) : null}
            </table>
        </div>
    );
}

function SinMovimientos({ mensaje }: { mensaje: string }) {
    return (
        <p className="py-6 text-center text-sm text-muted-foreground">
            {mensaje}
        </p>
    );
}

function columnasEntradas(entradas: Entrada[]): Columna<Entrada>[] {
    const recibidas = entradas.filter((entrada) => entrada.recibido);

    return [
        {
            id: 'registro',
            titulo: 'Registro',
            valor: (e) => e.fecha_registro,
            celda: (e) => e.fecha_registro ?? '—',
            pie: 'Totales',
        },
        {
            id: 'cantidad',
            titulo: 'Cant.',
            numerica: true,
            valor: (e) => e.cantidad,
            celda: (e) => e.cantidad,
            pie: sumar(entradas, (e) => e.cantidad),
        },
        {
            id: 'precio_unitario',
            titulo: 'Precio unit.',
            numerica: true,
            valor: (e) => e.precio_unitario,
            celda: (e) => formatoMoneda(e.precio_unitario),
        },
        {
            id: 'precio_total',
            titulo: 'Total',
            numerica: true,
            valor: (e) => e.precio_total,
            celda: (e) => formatoMoneda(e.precio_total),
            pie: formatoMoneda(sumar(entradas, (e) => e.precio_total)),
        },
        {
            id: 'codigo_compra',
            titulo: 'Compra',
            valor: (e) => e.codigo_compra,
            celda: (e) => e.codigo_compra ?? '—',
        },
        {
            id: 'estado',
            titulo: 'Estado',
            valor: (e) => (e.recibido ? (e.fecha_recibido ?? '') : null),
            celda: (e) =>
                e.recibido ? (
                    <Badge
                        variant="outline"
                        className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        Recibida {e.fecha_recibido}
                    </Badge>
                ) : (
                    <Badge
                        variant="outline"
                        className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300"
                    >
                        Pendiente
                    </Badge>
                ),
            pie: `${recibidas.length} de ${entradas.length} recibidas (${sumar(recibidas, (e) => e.cantidad)} uds)`,
        },
    ];
}

function columnasSalidas(salidas: Salida[]): Columna<Salida>[] {
    return [
        {
            id: 'fecha',
            titulo: 'Fecha',
            valor: (s) => s.fecha,
            celda: (s) => s.fecha ?? '—',
            pie: 'Totales',
        },
        {
            id: 'cantidad',
            titulo: 'Cant.',
            numerica: true,
            valor: (s) => s.cantidad,
            celda: (s) => s.cantidad,
            pie: sumar(salidas, (s) => s.cantidad),
        },
        {
            id: 'orden_trabajo',
            titulo: 'Orden de trabajo',
            valor: (s) => s.orden_trabajo_codigo,
            celda: (s) => s.orden_trabajo_codigo ?? '—',
        },
        {
            id: 'mantenimiento',
            titulo: 'Mantenimiento',
            valor: (s) => s.tipo_mantenimiento,
            celda: (s) =>
                s.tipo_mantenimiento
                    ? `${s.tipo_mantenimiento}${
                          s.estado_mantenimiento
                              ? ` · ${s.estado_mantenimiento}`
                              : ''
                      }`
                    : '—',
        },
    ];
}

export default function MovimientosItemModal({
    item,
    open,
    onOpenChange,
}: Props) {
    const [movimientos, setMovimientos] = useState<Movimientos | null>(null);
    const [fallo, setFallo] = useState(false);
    const itemId = item?.id ?? null;

    useEffect(() => {
        if (!open || itemId === null) {
            return;
        }

        const controlador = new AbortController();

        setMovimientos(null);
        setFallo(false);

        fetch(ItemController.movimientos.url(itemId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controlador.signal,
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error(String(respuesta.status));
                }

                return respuesta.json() as Promise<Movimientos>;
            })
            .then(setMovimientos)
            .catch((error: unknown) => {
                if (!(error instanceof DOMException)) {
                    setFallo(true);
                }
            });

        return () => controlador.abort();
    }, [open, itemId]);

    const columnasDeEntradas = useMemo(
        () => columnasEntradas(movimientos?.entradas ?? []),
        [movimientos],
    );
    const columnasDeSalidas = useMemo(
        () => columnasSalidas(movimientos?.salidas ?? []),
        [movimientos],
    );

    if (!item) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Movimientos del ítem</DialogTitle>
                    <DialogDescription>
                        {item.codigo} — {item.nombre}
                    </DialogDescription>
                </DialogHeader>

                {fallo ? (
                    <SinMovimientos mensaje="No se pudieron cargar los movimientos." />
                ) : movimientos === null ? (
                    <div className="space-y-2" data-test="movimientos-cargando">
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                ) : (
                    <Tabs defaultValue="entradas">
                        <TabsList>
                            <TabsTrigger
                                value="entradas"
                                data-test="movimientos-tab-entradas"
                            >
                                Entradas ({movimientos.entradas.length})
                            </TabsTrigger>
                            <TabsTrigger
                                value="salidas"
                                data-test="movimientos-tab-salidas"
                            >
                                Salidas ({movimientos.salidas.length})
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="entradas">
                            {movimientos.entradas.length === 0 ? (
                                <SinMovimientos mensaje="Este ítem aún no tiene entradas." />
                            ) : (
                                <TablaOrdenable
                                    filas={movimientos.entradas}
                                    columnas={columnasDeEntradas}
                                    testId="movimiento-entrada"
                                />
                            )}
                        </TabsContent>

                        <TabsContent value="salidas">
                            {movimientos.salidas.length === 0 ? (
                                <SinMovimientos mensaje="Este ítem aún no tiene salidas." />
                            ) : (
                                <TablaOrdenable
                                    filas={movimientos.salidas}
                                    columnas={columnasDeSalidas}
                                    testId="movimiento-salida"
                                />
                            )}
                        </TabsContent>
                    </Tabs>
                )}
            </DialogContent>
        </Dialog>
    );
}
