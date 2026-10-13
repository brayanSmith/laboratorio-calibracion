import { useRef, useState } from 'react';
import type { CSSProperties, RefObject } from 'react';

const ANCHO_MINIMO = 60;

type Resultado = {
    /** Va en el `<table>` para poder medir sus columnas. */
    tablaRef: RefObject<HTMLTableElement | null>;
    /** Anchos en píxeles por columna, o null mientras nadie haya redimensionado. */
    anchos: Record<string, number> | null;
    /** Estilo del `<table>`: fija el diseño solo después de redimensionar. */
    estiloTabla: CSSProperties | undefined;
    /** Devuelve el manejador de arrastre del borde derecho de una columna. */
    iniciarRedimension: (clave: string) => (evento: React.PointerEvent) => void;
    /** Vuelve a los anchos automáticos. */
    restablecer: () => void;
};

/**
 * Permite ajustar el ancho de las columnas de una tabla arrastrando su borde, al estilo
 * Excel. Mientras nadie arrastre, la tabla conserva su diseño automático; al empezar a
 * arrastrar mide el ancho real de todas las columnas y desde ahí manda el ancho en píxeles.
 *
 * `claves` son los identificadores de las columnas, en el mismo orden que las celdas de la
 * primera fila del encabezado.
 */
export function useResizableTable(claves: string[]): Resultado {
    const tablaRef = useRef<HTMLTableElement>(null);
    const [anchos, setAnchos] = useState<Record<string, number> | null>(null);

    const medirActuales = (): Record<string, number> | null => {
        const celdas = tablaRef.current?.querySelectorAll<HTMLTableCellElement>(
            'thead tr:first-child th',
        );

        if (!celdas) {
            return null;
        }

        return Object.fromEntries(
            claves.map((clave, indice) => [
                clave,
                Math.max(
                    ANCHO_MINIMO,
                    Math.round(
                        celdas[indice]?.getBoundingClientRect().width ?? 120,
                    ),
                ),
            ]),
        );
    };

    const iniciarRedimension =
        (clave: string) => (eventoInicial: React.PointerEvent) => {
            eventoInicial.preventDefault();

            const base = anchos ?? medirActuales();

            if (!base) {
                return;
            }

            setAnchos(base);

            const xInicial = eventoInicial.clientX;
            const anchoInicial = base[clave];

            const alMover = (evento: PointerEvent) => {
                const nuevoAncho = Math.max(
                    ANCHO_MINIMO,
                    anchoInicial + (evento.clientX - xInicial),
                );

                setAnchos((previos) => ({
                    ...(previos ?? base),
                    [clave]: nuevoAncho,
                }));
            };

            const alSoltar = () => {
                window.removeEventListener('pointermove', alMover);
                window.removeEventListener('pointerup', alSoltar);
            };

            window.addEventListener('pointermove', alMover);
            window.addEventListener('pointerup', alSoltar);
        };

    const estiloTabla: CSSProperties | undefined = anchos
        ? {
              tableLayout: 'fixed',
              width: Object.values(anchos).reduce(
                  (suma, ancho) => suma + ancho,
                  0,
              ),
          }
        : undefined;

    return {
        tablaRef,
        anchos,
        estiloTabla,
        iniciarRedimension,
        restablecer: () => setAnchos(null),
    };
}
