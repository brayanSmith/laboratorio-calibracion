import { router } from '@inertiajs/react';
import { FilterX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FilterOption } from '@/lib/data-table';

/** Una columna filtrable. Con `opciones` el filtro es una lista; si no, una caja de texto. */
export type ColumnaFiltro = {
    clave: string;
    etiqueta: string;
    opciones?: FilterOption[];
} | null;

type Props = {
    /** Una entrada por columna de la tabla, en orden; null para las que no se filtran. */
    columnas: ColumnaFiltro[];
    /** Filtros que el servidor aplicó a la lista actual. */
    valores: Record<string, string>;
    /** Dirección del listado, a la que se vuelve a pedir con los filtros. */
    url: string;
};

const ESPERA_MS = 400;

const selectClassName =
    'h-8 w-full min-w-24 rounded-md border border-input bg-background px-2 text-xs font-normal text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

function pedir(url: string, filtros: Record<string, string>) {
    const activos = Object.fromEntries(
        Object.entries(filtros).filter(([, valor]) => valor !== ''),
    );

    router.get(
        url,
        Object.keys(activos).length > 0 ? { filtro: activos } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/**
 * Fila de filtros para una tabla paginada en el servidor: al escribir (con una pequeña
 * espera) o elegir una opción, vuelve a pedir el listado con `filtro[columna]=valor`, de
 * modo que se filtra entre todos los registros y no solo entre los de la página visible.
 * Va dentro del `<thead>`.
 */
export function FilaFiltrosServidor({ columnas, valores, url }: Props) {
    const [textos, setTextos] = useState<Record<string, string>>(valores);
    const espera = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Si los filtros se limpian desde fuera, las cajas también.
    useEffect(() => {
        if (Object.keys(valores).length === 0) {
            setTextos({});
        }
    }, [valores]);

    useEffect(
        () => () => {
            if (espera.current) {
                clearTimeout(espera.current);
            }
        },
        [],
    );

    const cambiar = (clave: string, valor: string, conEspera: boolean) => {
        const siguientes = { ...textos, [clave]: valor };
        setTextos(siguientes);

        if (espera.current) {
            clearTimeout(espera.current);
        }

        if (conEspera) {
            espera.current = setTimeout(
                () => pedir(url, siguientes),
                ESPERA_MS,
            );
        } else {
            pedir(url, siguientes);
        }
    };

    return (
        <tr className="border-t bg-background">
            {columnas.map((columna, indice) => (
                <th
                    key={columna?.clave ?? `sin-filtro-${indice}`}
                    className="px-4 py-2 font-normal"
                >
                    {columna === null ? null : columna.opciones ? (
                        <select
                            value={textos[columna.clave] ?? ''}
                            onChange={(event) =>
                                cambiar(
                                    columna.clave,
                                    event.target.value,
                                    false,
                                )
                            }
                            className={selectClassName}
                            aria-label={`Filtrar ${columna.etiqueta}`}
                            data-test="server-column-filter"
                        >
                            <option value="">Todos</option>
                            {columna.opciones.map((opcion) => (
                                <option key={opcion.value} value={opcion.value}>
                                    {opcion.label}
                                </option>
                            ))}
                        </select>
                    ) : (
                        <Input
                            value={textos[columna.clave] ?? ''}
                            onChange={(event) =>
                                cambiar(columna.clave, event.target.value, true)
                            }
                            placeholder="Filtrar..."
                            aria-label={`Filtrar ${columna.etiqueta}`}
                            className="h-8 min-w-24 text-xs font-normal"
                            data-test="server-column-filter"
                        />
                    )}
                </th>
            ))}
        </tr>
    );
}

/** Botón para quitar todos los filtros; no se muestra si no hay ninguno aplicado. */
export function LimpiarFiltrosServidor({
    valores,
    url,
}: Pick<Props, 'valores' | 'url'>) {
    if (Object.keys(valores).length === 0) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => pedir(url, {})}
            data-test="server-clear-filters"
        >
            <FilterX className="h-4 w-4" /> Limpiar filtros
        </Button>
    );
}
