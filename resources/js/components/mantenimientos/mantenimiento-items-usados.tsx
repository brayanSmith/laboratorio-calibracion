import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ColumnResizeHandle from '@/components/column-resize-handle';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import { destroy } from '@/routes/item-mantenimientos';
import type { MantenimientoItemOption, MantenimientoItemUsado } from '@/types';

type Props = {
    itemsUsados: MantenimientoItemUsado[];
    items: MantenimientoItemOption[];
    errors: Partial<Record<string, string>>;
};

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
    itemsUsados,
    items,
    errors,
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
        cantidad: 96,
    });

    const actualizarFila = (key: number, cambios: Partial<FilaBorrador>) =>
        setFilas((prev) =>
            prev.map((fila) =>
                fila.key === key ? { ...fila, ...cambios } : fila,
            ),
        );

    const opciones = items.map((item) => ({
        id: item.id,
        label: `${item.codigo} · ${item.nombre}`,
    }));

    return (
        <div className="min-w-0 space-y-2">
            <Label>Ítems usados</Label>

            <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
                <table
                    className="text-sm"
                    style={{
                        width: anchos.item + anchos.cantidad + 40,
                        tableLayout: 'fixed',
                    }}
                >
                    <colgroup>
                        <col style={{ width: anchos.item }} />
                        <col style={{ width: anchos.cantidad }} />
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
                                Cantidad
                                <ColumnResizeHandle
                                    label="Cantidad"
                                    onPointerDown={iniciarRedimension(
                                        'cantidad',
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
                                <td className="truncate px-3 py-2">
                                    {itemUsado.cantidad}
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

                        {filas.map((fila, index) => (
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
                                    <Input
                                        name={`nuevos_items_usados[${index}][cantidad]`}
                                        value={fila.cantidad}
                                        onChange={(event) =>
                                            actualizarFila(fila.key, {
                                                cantidad: event.target.value,
                                            })
                                        }
                                        type="number"
                                        step="0.01"
                                        min="0.01"
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
                                                        otra.key !== fila.key,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
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
