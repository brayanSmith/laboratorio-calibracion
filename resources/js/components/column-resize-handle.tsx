type Props = {
    onPointerDown: (event: React.PointerEvent) => void;
    label: string;
    /** Opcional: el doble clic suele usarse para volver al ancho automático. */
    onDoubleClick?: () => void;
};

/** Separador de columna visible (línea delgada) con una zona de arrastre más
 * ancha para redimensionar, al estilo Excel. Va dentro de un <th relative>. */
export default function ColumnResizeHandle({
    onPointerDown,
    label,
    onDoubleClick,
}: Props) {
    return (
        <div
            role="separator"
            aria-label={`Redimensionar columna ${label}`}
            onPointerDown={onPointerDown}
            onDoubleClick={onDoubleClick}
            className="group absolute top-0 right-0 -mr-1 h-full w-2 cursor-col-resize touch-none select-none"
        >
            <div className="mx-auto h-full w-px bg-border group-hover:w-0.5 group-hover:bg-primary" />
        </div>
    );
}

/**
 * `<colgroup>` con el ancho de cada columna, para las tablas redimensionables (ver
 * useResizableTable). No pinta nada mientras no se haya redimensionado.
 */
export function ColgroupAnchos({
    claves,
    anchos,
}: {
    claves: string[];
    anchos: Record<string, number> | null;
}) {
    if (!anchos) {
        return null;
    }

    return (
        <colgroup>
            {claves.map((clave) => (
                <col key={clave} style={{ width: anchos[clave] }} />
            ))}
        </colgroup>
    );
}
