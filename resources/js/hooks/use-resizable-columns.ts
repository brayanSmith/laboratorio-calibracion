import { useState } from 'react';

/** Arrastra el borde derecho de una columna (como en Excel) para ajustar su ancho.
 * Útil en tablas con `<colgroup>` + anchos en píxeles en vez de table-fixed. */
export function useResizableColumns<Columna extends string>(
    anchosIniciales: Record<Columna, number>,
    anchoMinimo = 60,
) {
    const [anchos, setAnchos] = useState(anchosIniciales);

    const iniciarRedimension =
        (columna: Columna) => (eventoInicial: React.PointerEvent) => {
            eventoInicial.preventDefault();
            const xInicial = eventoInicial.clientX;
            const anchoInicial = anchos[columna];

            const alMover = (evento: PointerEvent) => {
                const nuevoAncho = Math.max(
                    anchoMinimo,
                    anchoInicial + (evento.clientX - xInicial),
                );
                setAnchos((prev) => ({ ...prev, [columna]: nuevoAncho }));
            };

            const alSoltar = () => {
                window.removeEventListener('pointermove', alMover);
                window.removeEventListener('pointerup', alSoltar);
            };

            window.addEventListener('pointermove', alMover);
            window.addEventListener('pointerup', alSoltar);
        };

    return { anchos, iniciarRedimension };
}
