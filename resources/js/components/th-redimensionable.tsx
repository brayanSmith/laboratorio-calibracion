import type { ReactNode } from 'react';
import ColumnResizeHandle from '@/components/column-resize-handle';
import type { useResizableTable } from '@/hooks/use-resizable-table';

type Props = {
    /** Identificador de la columna, el mismo que se pasó a useResizableTable. */
    clave: string;
    redimension: Pick<
        ReturnType<typeof useResizableTable>,
        'iniciarRedimension' | 'restablecer'
    >;
    children: ReactNode;
};

/** Encabezado de columna de una tabla simple, con el borde arrastrable para cambiar su ancho. */
export default function ThRedimensionable({
    clave,
    redimension,
    children,
}: Props) {
    return (
        <th className="relative px-4 py-3 font-medium">
            {children}
            <ColumnResizeHandle
                label={clave}
                onPointerDown={redimension.iniciarRedimension(clave)}
                onDoubleClick={redimension.restablecer}
            />
        </th>
    );
}
