import { router, useHttp } from '@inertiajs/react';
import { marcarLeida } from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import type { ResumenImportacion } from '@/types';

type MarcarLeida = (
    importacion: ResumenImportacion,
    leida: boolean,
) => Promise<ResumenImportacion | undefined>;

/**
 * Marca el resultado de una importación como leído o no leído y actualiza el globito del
 * botón Importar (la prop compartida importacionesSinLeer), sin recargar la página.
 */
export function useMarcarImportacionLeida(): MarcarLeida {
    // Una instancia por valor: los datos de useHttp son estado y no cambian dentro de la misma llamada.
    const comoLeida = useHttp<{ leida: boolean }, ResumenImportacion>({
        leida: true,
    });
    const comoNoLeida = useHttp<{ leida: boolean }, ResumenImportacion>({
        leida: false,
    });

    return (importacion, leida) =>
        (leida ? comoLeida : comoNoLeida)
            .patch(marcarLeida(importacion.id).url)
            .then((actualizada) => {
                router.reload({ only: ['importacionesSinLeer'], async: true });

                return actualizada;
            })
            .catch(() => undefined);
}
