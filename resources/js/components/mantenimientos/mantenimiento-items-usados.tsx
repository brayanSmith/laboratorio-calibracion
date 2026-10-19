import { router } from '@inertiajs/react';
import { Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import MantenimientoController from '@/actions/App/Modules/Mantenimientos/Http/Controllers/MantenimientoController';
import ColumnResizeHandle from '@/components/column-resize-handle';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import { destroy } from '@/routes/item-mantenimientos';
import type { MantenimientoItemOption, MantenimientoItemUsado } from '@/types';

type Props = {
    mantenimientoId: number;
    itemsUsados: MantenimientoItemUsado[];
    items: MantenimientoItemOption[];
    errors: Partial<Record<string, string>>;
    /** Avisa si alguna fila nueva tiene un ítem sin stock, para marcar "Hacen falta repuestos". */
    onSinStockChange: (haySinStock: boolean) => void;
};

const formatoMoneda = (valor: number) =>
    valor.toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

type FilaBorrador = {
    key: number;
    itemId: string;
    cantidad: string;
};

/** Relación 1:* (un mantenimiento puede usar varios ítems). Las filas nuevas quedan
 * disponibles para registrarse al presionar el botón "Guardar" de la modal. Las
 * columnas se pueden redimensionar arrastrando su separador, al estilo Excel, y la
 * tabla tiene su propio scroll horizontal (no afecta al resto de la modal). */
export default function MantenimientoItemsUsados({
    mantenimientoId,
    itemsUsados,
    items,
    errors,
    onSinStockChange,
}: Props) {
    const siguienteKey = useRef(0);
    const filaVacia = (): FilaBorrador => ({
        key: siguienteKey.current++,
        itemId: '',
        cantidad: '',
    });

    const [filas, setFilas] = useState<FilaBorrador[]>(() => [filaVacia()]);
    const { anchos, iniciarRedimension } = useResizableColumns({
        item: 280,
        stock: 110,
        cantidad: 96,
        precio: 120,
        subtotal: 120,
    });

    const itemDe = (fila: FilaBorrador) =>
        items.find((item) => String(item.id) === fila.itemId) ?? null;

    // Lo que queda del stock del ítem, descontando lo pedido en las otras filas nuevas.
    const stockDisponible = (fila: FilaBorrador) => {
        const item = itemDe(fila);

        if (!item || item.stock <= 0) {
            return 0;
        }

        const enOtrasFilas = filas
            .filter(
                (otra) => otra.key !== fila.key && otra.itemId === fila.itemId,
            )
            .reduce((total, otra) => total + (Number(otra.cantidad) || 0), 0);

        return Math.max(0, Math.round((item.stock - enOtrasFilas) * 100) / 100);
    };

    const cambiarCantidad = (fila: FilaBorrador, valor: string) => {
        const item = itemDe(fila);

        // Sin stock no hay tope: se registra la cantidad que hace falta.
        if (item === null || item.stock <= 0) {
            actualizarFila(fila.key, { cantidad: valor });

            return;
        }

        const maximo = stockDisponible(fila);

        actualizarFila(fila.key, {
            cantidad:
                valor !== '' && Number(valor) > maximo ? String(maximo) : valor,
        });
    };

    const actualizarFila = (key: number, cambios: Partial<FilaBorrador>) =>
        setFilas((prev) =>
            prev.map((fila) =>
                fila.key === key ? { ...fila, ...cambios } : fila,
            ),
        );

    const [actualizando, setActualizando] = useState(false);

    // Recarga el stock actual de los ítems y reevalúa los ya registrados sin stock, sin
    // perder lo que ya se escribió en la modal.
    const actualizarStock = () =>
        router.post(
            MantenimientoController.actualizarStock.url(mantenimientoId),
            {},
            {
                only: ['items', 'mantenimientos'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setActualizando(true),
                onFinish: () => setActualizando(false),
            },
        );

    const haySinStock = filas.some((fila) => {
        const item = itemDe(fila);

        return item !== null && item.stock <= 0;
    });

    useEffect(() => {
        onSinStockChange(haySinStock);
    }, [haySinStock, onSinStockChange]);

    const opciones = items.map((item) => ({
        id: item.id,
        label: `${item.codigo} · ${item.nombre} (stock: ${item.stock})`,
    }));

    return (
        <div className="min-w-0 space-y-2">
            <div className="flex items-center justify-between gap-2">
                <Label>Ítems usados</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={actualizando}
                    onClick={actualizarStock}
                    data-test="actualizar-stock-items-button"
                >
                    <RefreshCw
                        className={`h-3.5 w-3.5 ${actualizando ? 'animate-spin' : ''}`}
                    />
                    Actualizar
                </Button>
            </div>

            <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
                <table
                    className="text-sm"
                    style={{
                        width:
                            anchos.item +
                            anchos.stock +
                            anchos.cantidad +
                            anchos.precio +
                            anchos.subtotal +
                            40,
                        tableLayout: 'fixed',
                    }}
                >
                    <colgroup>
                        <col style={{ width: anchos.item }} />
                        <col style={{ width: anchos.stock }} />
                        <col style={{ width: anchos.cantidad }} />
                        <col style={{ width: anchos.precio }} />
                        <col style={{ width: anchos.subtotal }} />
                        <col style={{ width: 40 }} />
                    </colgroup>
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="relative px-3 py-2 font-medium">
                                Ítem
                                <ColumnResizeHandle
                                    label="Ítem"
                                    onPointerDown={iniciarRedimension('item')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Con stock
                                <ColumnResizeHandle
                                    label="Con stock"
                                    onPointerDown={iniciarRedimension('stock')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Cantidad
                                <ColumnResizeHandle
                                    label="Cantidad"
                                    onPointerDown={iniciarRedimension(
                                        'cantidad',
                                    )}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Precio unit.
                                <ColumnResizeHandle
                                    label="Precio unitario"
                                    onPointerDown={iniciarRedimension('precio')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Subtotal
                                <ColumnResizeHandle
                                    label="Subtotal"
                                    onPointerDown={iniciarRedimension(
                                        'subtotal',
                                    )}
                                />
                            </th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody
                        className="divide-y"
                        data-test="mantenimiento-items-usados-list"
                    >
                        {itemsUsados.map((itemUsado) => (
                            <tr key={`saved-${itemUsado.id}`}>
                                <td className="truncate px-3 py-2 font-medium">
                                    {itemUsado.item_codigo} ·{' '}
                                    {itemUsado.item_nombre}
                                </td>
                                <td className="px-3 py-2">
                                    <label
                                        className="flex items-center gap-2 text-xs"
                                        data-test="item-usado-guardado-con-stock"
                                    >
                                        <Checkbox
                                            checked={itemUsado.con_stock}
                                            disabled
                                            aria-label="Con stock"
                                        />
                                        <span
                                            className={
                                                itemUsado.con_stock
                                                    ? ''
                                                    : 'font-medium text-destructive'
                                            }
                                        >
                                            {itemUsado.con_stock
                                                ? 'Con stock'
                                                : 'Sin stock'}
                                        </span>
                                    </label>
                                </td>
                                <td className="truncate px-3 py-2">
                                    {itemUsado.cantidad}
                                </td>
                                <td className="truncate px-3 py-2 tabular-nums">
                                    {formatoMoneda(itemUsado.precio_unitario)}
                                </td>
                                <td className="truncate px-3 py-2 font-medium tabular-nums">
                                    {formatoMoneda(itemUsado.subtotal)}
                                </td>
                                <td className="px-3 py-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-6 w-6"
                                        aria-label="Quitar ítem"
                                        onClick={() =>
                                            router.delete(
                                                destroy.url(itemUsado.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        ))}

                        {filas.map((fila, index) => {
                            const item = itemDe(fila);
                            const sinStock = item !== null && item.stock <= 0;
                            const disponible = stockDisponible(fila);

                            // Un ítem sin stock no se registra como usado: sus campos no viajan.
                            return (
                                <tr
                                    key={`draft-${fila.key}`}
                                    data-test="item-usado-fila-nueva"
                                >
                                    <td className="px-3 py-2">
                                        <Combobox
                                            name={`nuevos_items_usados[${index}][item_id]`}
                                            value={fila.itemId}
                                            onValueChange={(value) =>
                                                actualizarFila(fila.key, {
                                                    itemId: value,
                                                })
                                            }
                                            options={opciones}
                                            placeholder="Selecciona un ítem"
                                            searchPlaceholder="Buscar ítem..."
                                            className="h-7 w-full rounded-none border-0 bg-transparent p-0 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `nuevos_items_usados.${index}.item_id`
                                                ]
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        {item ? (
                                            <label
                                                className="flex items-center gap-2 text-xs"
                                                data-test="item-usado-con-stock"
                                            >
                                                <Checkbox
                                                    checked={!sinStock}
                                                    disabled
                                                    aria-label="Con stock"
                                                />
                                                <span
                                                    className={
                                                        sinStock
                                                            ? 'font-medium text-destructive'
                                                            : 'tabular-nums'
                                                    }
                                                >
                                                    {sinStock
                                                        ? 'Sin stock'
                                                        : `Stock: ${item.stock}`}
                                                </span>
                                            </label>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Input
                                            name={`nuevos_items_usados[${index}][cantidad]`}
                                            value={fila.cantidad}
                                            onChange={(event) =>
                                                cambiarCantidad(
                                                    fila,
                                                    event.target.value,
                                                )
                                            }
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            max={
                                                item && !sinStock
                                                    ? disponible
                                                    : undefined
                                            }
                                            className="h-7 w-full rounded-none border-0 bg-transparent p-0 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `nuevos_items_usados.${index}.cantidad`
                                                ]
                                            }
                                        />
                                    </td>
                                    <td className="truncate px-3 py-2 text-xs tabular-nums">
                                        {item
                                            ? formatoMoneda(
                                                  item.precio_unitario,
                                              )
                                            : '—'}
                                    </td>
                                    <td className="truncate px-3 py-2 text-xs font-medium tabular-nums">
                                        {item
                                            ? formatoMoneda(
                                                  (Number(fila.cantidad) || 0) *
                                                      item.precio_unitario,
                                              )
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            aria-label="Quitar fila"
                                            disabled={filas.length === 1}
                                            onClick={() =>
                                                setFilas((prev) =>
                                                    prev.filter(
                                                        (otra) =>
                                                            otra.key !==
                                                            fila.key,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setFilas((prev) => [...prev, filaVacia()])}
                data-test="agregar-fila-item-usado-button"
            >
                <Plus className="h-4 w-4" /> Agregar otro ítem
            </Button>
        </div>
    );
}
