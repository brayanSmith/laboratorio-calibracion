import { useCallback, useEffect, useRef, useState } from 'react';

export type DesplazamientoHorizontal = {
    /** Si el contenido es más ancho que el contenedor (hay algo por deslizar). */
    desborda: boolean;
    /** Ancho visible sobre el ancho total, de 0 a 1: el tamaño del "pulgar". */
    visible: number;
    /** Cuánto se ha deslizado, de 0 (inicio) a 1 (final). */
    avance: number;
};

/**
 * Sigue el desplazamiento horizontal de un contenedor, para dibujar un indicador propio
 * donde el navegador oculta la barra de scroll (en móviles casi siempre).
 */
export function useDesplazamientoHorizontal<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [estado, setEstado] = useState<DesplazamientoHorizontal>({
        desborda: false,
        visible: 1,
        avance: 0,
    });

    const medir = useCallback(() => {
        const elemento = ref.current;

        if (!elemento) {
            return;
        }

        const { scrollLeft, scrollWidth, clientWidth } = elemento;
        const sobrante = scrollWidth - clientWidth;

        setEstado({
            desborda: sobrante > 1,
            visible: scrollWidth > 0 ? clientWidth / scrollWidth : 1,
            avance: sobrante > 0 ? scrollLeft / sobrante : 0,
        });
    }, []);

    useEffect(() => {
        const elemento = ref.current;

        if (!elemento) {
            return;
        }

        medir();

        const observador = new ResizeObserver(medir);
        observador.observe(elemento);
        Array.from(elemento.children).forEach((hijo) =>
            observador.observe(hijo),
        );

        return () => observador.disconnect();
    }, [medir]);

    return { ref, onScroll: medir, ...estado };
}
