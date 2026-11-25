import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { verDetalleNoEliminables } from '@/components/detalle-no-eliminables-dialog';
import type { FlashToast } from '@/types/ui';

/** Con detalle, el aviso dura más: el usuario debe alcanzar a abrirlo. */
const DURACION_CON_DETALLE_MS = 12000;

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const detalle = data.detalle;

            if (detalle && detalle.registros.length > 0) {
                toast[data.type](data.message, {
                    duration: DURACION_CON_DETALLE_MS,
                    action: {
                        label: 'Ver detalle',
                        onClick: () => verDetalleNoEliminables(detalle),
                    },
                });

                return;
            }

            toast[data.type](data.message);
        });
    }, []);
}
