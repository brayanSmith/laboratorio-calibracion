type Props = {
    onPointerDown: (event: React.PointerEvent) => void;
    label: string;
};

/** Separador de columna visible (línea delgada) con una zona de arrastre más
 * ancha para redimensionar, al estilo Excel. Va dentro de un <th relative>. */
export default function ColumnResizeHandle({ onPointerDown, label }: Props) {
    return (
        <div
            role="separator"
            aria-label={`Redimensionar columna ${label}`}
            onPointerDown={onPointerDown}
            className="group absolute top-0 right-0 -mr-1 h-full w-2 cursor-col-resize touch-none select-none"
        >
            <div className="mx-auto h-full w-px bg-border group-hover:w-0.5 group-hover:bg-primary" />
        </div>
    );
}
