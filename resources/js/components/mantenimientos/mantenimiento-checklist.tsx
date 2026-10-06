import { CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';
import ColumnResizeHandle from '@/components/column-resize-handle';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import type { MantenimientoCheckListItem } from '@/types';

type Props = {
    checklist: MantenimientoCheckListItem[];
};

type FilaEstado = {
    cumple: boolean;
    observacion: string;
};

/** Checklist copiado del tipo de equipo al agendar el mantenimiento (ver
 * OrdenTrabajoController::store()). Se edita como tabla dentro de la modal; se guarda
 * junto con el resto de la gestión al presionar el botón "Guardar" de la modal. Las
 * columnas se pueden redimensionar arrastrando su separador, al estilo Excel. */
export default function MantenimientoChecklist({ checklist }: Props) {
    const [filas, setFilas] = useState<Record<number, FilaEstado>>(() =>
        Object.fromEntries(
            checklist.map((item) => [
                item.id,
                { cumple: item.cumple, observacion: item.observacion ?? '' },
            ]),
        ),
    );
    const { anchos, iniciarRedimension } = useResizableColumns({
        cumple: 80,
        item: 220,
        observacion: 260,
    });

    if (checklist.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <Label>Checklist</Label>

            <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
                <table
                    className="text-sm"
                    style={{
                        width: anchos.item + anchos.cumple + anchos.observacion,
                        tableLayout: 'fixed',
                    }}
                >
                    <colgroup>
                        <col style={{ width: anchos.cumple }} />
                        <col style={{ width: anchos.item }} />
                        <col style={{ width: anchos.observacion }} />
                    </colgroup>
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="relative px-3 py-2 font-medium">
                                Cumple
                                <ColumnResizeHandle
                                    label="Cumple"
                                    onPointerDown={iniciarRedimension('cumple')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Ítem
                                <ColumnResizeHandle
                                    label="Ítem"
                                    onPointerDown={iniciarRedimension('item')}
                                />
                            </th>
                            <th className="px-3 py-2 font-medium">
                                Observación
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        className="divide-y"
                        data-test="mantenimiento-checklist-list"
                    >
                        {checklist.map((item, index) => {
                            const fila = filas[item.id];

                            return (
                                <tr
                                    key={item.id}
                                    className={
                                        fila.cumple
                                            ? 'bg-emerald-50 dark:bg-emerald-950/30'
                                            : 'bg-red-50 dark:bg-red-950/30'
                                    }
                                    data-test="checklist-item-row"
                                >
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setFilas((prev) => ({
                                                    ...prev,
                                                    [item.id]: {
                                                        ...prev[item.id],
                                                        cumple: !prev[item.id]
                                                            .cumple,
                                                    },
                                                }))
                                            }
                                            aria-label={`Cumple: ${item.nombre}`}
                                            data-test="checklist-cumple-toggle"
                                        >
                                            {fila.cumple ? (
                                                <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                            ) : (
                                                <XCircle className="h-5 w-5 text-red-500" />
                                            )}
                                        </button>
                                    </td>
                                    <td className="truncate px-3 py-2">
                                        {item.nombre}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Textarea
                                            value={fila.observacion}
                                            onChange={(event) =>
                                                setFilas((prev) => ({
                                                    ...prev,
                                                    [item.id]: {
                                                        ...prev[item.id],
                                                        observacion:
                                                            event.target.value,
                                                    },
                                                }))
                                            }
                                            placeholder="Observación (opcional)"
                                            rows={1}
                                            className="field-sizing-content max-h-20 min-h-7 w-full resize-none overflow-y-auto rounded-none border-0 bg-transparent p-1 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                                        />
                                        <input
                                            type="hidden"
                                            name={`checklist[${index}][id]`}
                                            value={item.id}
                                        />
                                        <input
                                            type="hidden"
                                            name={`checklist[${index}][cumple]`}
                                            value={fila.cumple ? '1' : '0'}
                                        />
                                        <input
                                            type="hidden"
                                            name={`checklist[${index}][observacion]`}
                                            value={fila.observacion}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
