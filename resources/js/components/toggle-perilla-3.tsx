/**
 * Cuánto se desplaza la perilla dentro del track: una posición por cada opción,
 * desplazada el 100% de su propio ancho (no del track), así que coincide con el
 * ancho de cada franja sin importar el tamaño real en píxeles. Pensado para
 * exactamente 3 opciones (ver grid-cols-3 y w-1/3 más abajo).
 */
const traslacionPerilla = [
    'translate-x-0',
    'translate-x-full',
    'translate-x-[200%]',
];

type Opcion = {
    value: string;
    label: string;
};

type Props = {
    value: string;
    opciones: readonly Opcion[];
    /** Color de fondo de la perilla para cada valor, ej. { AGENDADO: "bg-emerald-500" }. */
    colorPerilla: Record<string, string>;
    onSeleccionar: (valor: string) => void;
    ariaLabel: string;
};

/** Toggle agrupado de 3 posiciones, con una perilla de color que se desliza entre ellas. */
export default function TogglePerilla3({
    value,
    opciones,
    colorPerilla,
    onSeleccionar,
    ariaLabel,
}: Props) {
    const indice = opciones.findIndex((opcion) => opcion.value === value);

    return (
        <div
            role="group"
            aria-label={ariaLabel}
            className="relative h-5 w-14 rounded-full border bg-muted p-0.5"
        >
            <div className="grid h-full grid-cols-3">
                {opciones.map((opcion) => (
                    <button
                        key={opcion.value}
                        type="button"
                        aria-label={opcion.label}
                        aria-pressed={value === opcion.value}
                        onClick={() => onSeleccionar(opcion.value)}
                        className="h-full w-full cursor-pointer"
                    />
                ))}
            </div>

            <span
                aria-hidden="true"
                className={`pointer-events-none absolute inset-y-0.5 left-0.5 w-1/3 rounded-full shadow transition-transform duration-150 ${traslacionPerilla[indice]} ${colorPerilla[value]}`}
            />
        </div>
    );
}
