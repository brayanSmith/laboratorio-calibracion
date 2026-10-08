import ColumnResizeHandle from '@/components/column-resize-handle';
import DetalleAlcanceTableRow from '@/components/alcances-medicion/detalle-alcance-table-row';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import type {
    AlcanceMedicionUnidadOpcion,
    DetalleAlcanceMedicion,
} from '@/types';

export type FilaDetalleAlcance = {
    key: number;
    detalle?: DetalleAlcanceMedicion;
};

type Props = {
    filas: FilaDetalleAlcance[];
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
    errors: Partial<Record<string, string>>;
    onRemove: (key: number) => void;
};

const columnas = [
    { id: 'unidad', titulo: 'Unidad de medida' },
    { id: 'valor', titulo: 'Valor del instrumento' },
    { id: 'emp', titulo: 'EMP' },
    { id: 'incertidumbre', titulo: 'Incertidumbre' },
] as const;

/** Tabla de detalles al estilo Excel: celdas sin borde y columnas que se redimensionan
 * arrastrando su separador. Cada fila envía sus valores como `detalles[i][campo]`. */
export default function DetallesAlcanceTabla({
    filas,
    unidadesMedida,
    errors,
    onRemove,
}: Props) {
    const { anchos, iniciarRedimension } = useResizableColumns({
        unidad: 260,
        valor: 170,
        emp: 110,
        incertidumbre: 140,
    });
    const anchoTotal =
        anchos.unidad + anchos.valor + anchos.emp + anchos.incertidumbre + 40;

    return (
        <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
            <table
                className="text-sm"
                style={{ width: anchoTotal, tableLayout: 'fixed' }}
            >
                <colgroup>
                    {columnas.map((columna) => (
                        <col
                            key={columna.id}
                            style={{ width: anchos[columna.id] }}
                        />
                    ))}
                    <col style={{ width: 40 }} />
                </colgroup>
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {columnas.map((columna) => (
                            <th
                                key={columna.id}
                                className="relative px-3 py-2 font-medium"
                            >
                                {columna.titulo}
                                <ColumnResizeHandle
                                    label={columna.titulo}
                                    onPointerDown={iniciarRedimension(
                                        columna.id,
                                    )}
                                />
                            </th>
                        ))}
                        <th className="px-3 py-2" />
                    </tr>
                </thead>
                <tbody
                    className="divide-y"
                    data-test="alcance-medicion-detalles"
                >
                    {filas.map(({ key, detalle }, index) => (
                        <DetalleAlcanceTableRow
                            key={key}
                            detalle={detalle}
                            index={index}
                            unidadesMedida={unidadesMedida}
                            errors={errors}
                            onRemove={() => onRemove(key)}
                        />
                    ))}

                    {filas.length === 0 ? (
                        <tr>
                            <td
                                colSpan={columnas.length + 1}
                                className="px-3 py-4 text-center text-muted-foreground"
                            >
                                Sin detalles. Usa "Agregar otro detalle" para
                                añadirlos.
                            </td>
                        </tr>
                    ) : null}
                </tbody>
            </table>
        </div>
    );
}
