import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Devuelve la ruta (pathname) de la página a la que se está navegando, o null
 * cuando no hay ninguna navegación en curso. Ignora las peticiones asíncronas
 * y los prefetch para no marcar como "cargando" lo que el usuario no pidió.
 *
 * Se escucha `before` y no `start` porque, si el enlace ya se estaba precargando
 * (prefetch al pasar el mouse), Inertia reutiliza esa petición y no dispara `start`.
 */
export function useNavegacionPendiente(): string | null {
    const [rutaPendiente, setRutaPendiente] = useState<string | null>(null);

    useEffect(() => {
        const limpiar = () => setRutaPendiente(null);

        const quitarBefore = router.on('before', (event) => {
            const { visit } = event.detail;

            if (visit.method !== 'get' || visit.async || visit.prefetch) {
                return;
            }

            setRutaPendiente(visit.url.pathname);
        });

        // Una visita interrumpida por otro clic no debe borrar el spinner del nuevo destino.
        const quitarFinish = router.on('finish', (event) => {
            const { visit } = event.detail;

            if (visit.async || visit.prefetch) {
                return;
            }

            setRutaPendiente((actual) =>
                actual === visit.url.pathname ? null : actual,
            );
        });

        const quitarNavigate = router.on('navigate', limpiar);
        const quitarHttpException = router.on('httpException', limpiar);
        const quitarNetworkError = router.on('networkError', limpiar);

        return () => {
            quitarBefore();
            quitarFinish();
            quitarNavigate();
            quitarHttpException();
            quitarNetworkError();
        };
    }, []);

    return rutaPendiente;
}
