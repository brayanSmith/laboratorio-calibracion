/** Separación entre el punto del clic y el menú, y entre el menú y el borde de la ventana. */
const SEPARACION = 8;

/** Elementos de la fila que tienen su propio clic: no abren el menú de acciones. */
export const SELECTOR_INTERACTIVO =
    'button, a, input, select, textarea, label, [role="checkbox"], [role="combobox"], [data-sin-menu-fila]';

/**
 * Dónde dibujar el menú de acciones de una fila: centrado bajo el punto del clic, sin
 * salirse de la ventana, y por encima del clic cuando no cabe debajo.
 */
export function posicionMenuFila(
    clic: { x: number; y: number },
    menu: { ancho: number; alto: number },
    ventana: { ancho: number; alto: number },
): { left: number; top: number } {
    const maximoLeft = ventana.ancho - menu.ancho - SEPARACION;
    const left = Math.max(
        SEPARACION,
        Math.min(clic.x - menu.ancho / 2, maximoLeft),
    );

    const debajo = clic.y + SEPARACION;
    const cabeDebajo = debajo + menu.alto + SEPARACION <= ventana.alto;
    const top = cabeDebajo
        ? debajo
        : Math.max(SEPARACION, clic.y - SEPARACION - menu.alto);

    return { left, top };
}
