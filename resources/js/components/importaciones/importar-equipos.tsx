import { router, useHttp, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    enCurso,
    show,
} from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionEquipoController';
import BotonAtajo from '@/components/boton-atajo';
import AvanceImportacionModal, {
    importacionEnCurso,
} from '@/components/importaciones/avance-importacion-modal';
import RelacionarColumnasModal from '@/components/importaciones/relacionar-columnas-modal';
import SubirCsvModal from '@/components/importaciones/subir-csv-modal';
import { useMarcarImportacionLeida } from '@/hooks/use-marcar-importacion-leida';
import type { ArchivoImportacion, ResumenImportacion } from '@/types';

/** Cada cuánto se consulta el avance de una importación en curso. */
const INTERVALO_AVANCE_MS = 2000;

type Paso = 'archivo' | 'mapeo' | 'avance';

/**
 * Botón "Importar" del menú superior: sube un CSV de equipos, relaciona sus columnas con
 * los campos y lo importa en segundo plano. Mientras corre, el botón muestra un spinner y
 * al terminar avisa con el resultado, aunque el usuario haya cerrado la ventana. El globito
 * cuenta las importaciones terminadas cuyo resultado el usuario aún no ha revisado.
 */
export default function ImportarEquipos() {
    const { importacionesSinLeer } = usePage().props;
    const [paso, setPaso] = useState<Paso | null>(null);
    // El aviso de "terminada" se arma dentro del intervalo, que no ve el paso actualizado.
    const pasoActual = useRef<Paso | null>(null);

    useEffect(() => {
        pasoActual.current = paso;
    }, [paso]);

    const marcarLeida = useMarcarImportacionLeida();
    const [archivo, setArchivo] = useState<ArchivoImportacion | null>(null);
    const [importacion, setImportacion] = useState<ResumenImportacion | null>(
        null,
    );
    const { get: consultarEnCurso } = useHttp<
        Record<string, never>,
        { importacion: ResumenImportacion | null }
    >();
    const { get: consultar } = useHttp<
        Record<string, never>,
        ResumenImportacion
    >();

    const corriendo = importacion !== null && importacionEnCurso(importacion);

    // Retoma la importación en curso al recargar la página o cambiar de pestaña.
    useEffect(() => {
        consultarEnCurso(enCurso().url)
            .then((respuesta) => {
                if (respuesta?.importacion) {
                    setImportacion(respuesta.importacion);
                }
            })
            .catch(() => undefined);
        // Solo al montar el menú.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!importacion || !corriendo) {
            return;
        }

        const intervalo = window.setInterval(() => {
            consultar(show(importacion.id).url)
                .then((actual) => {
                    if (!actual) {
                        return;
                    }

                    setImportacion(actual);

                    if (!importacionEnCurso(actual)) {
                        avisarResultado(actual);
                    }
                })
                .catch(() => undefined);
        }, INTERVALO_AVANCE_MS);

        return () => window.clearInterval(intervalo);
        // Se reinicia solo cuando cambia la importación o deja de estar en curso.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [importacion?.id, corriendo]);

    const marcarComoLeida = (resultado: ResumenImportacion) => {
        void marcarLeida(resultado, true).then((actualizada) => {
            if (actualizada) {
                setImportacion(actualizada);
            }
        });
    };

    const avisarResultado = (resultado: ResumenImportacion) => {
        // Si la está mirando en la ventana de avance, ya vio el resultado.
        if (pasoActual.current === 'avance') {
            marcarComoLeida(resultado);
        } else {
            router.reload({ only: ['importacionesSinLeer'], async: true });
        }

        const verDetalle = {
            label: 'Ver detalle',
            onClick: () => {
                setPaso('avance');
                marcarComoLeida(resultado);
            },
        };

        if (resultado.estado === 'FALLIDA') {
            toast.error('La importación de equipos falló.', {
                action: verDetalle,
            });
        } else if (resultado.filas_con_error > 0) {
            toast.warning(
                `Importación terminada: ${resultado.filas_creadas} equipos importados y ${resultado.filas_con_error} filas con errores.`,
                { action: verDetalle, duration: 10000 },
            );
        } else {
            toast.success(
                `Importación terminada: ${resultado.filas_creadas} equipos importados.`,
                { action: verDetalle },
            );
        }

        // Si el usuario está viendo el listado de equipos, se actualiza con los nuevos.
        if (window.location.pathname.startsWith('/equipos')) {
            router.reload();
        }
    };

    const abrir = () => setPaso(corriendo ? 'avance' : 'archivo');

    const cerrarSi = (pasoActual: Paso) => (abierta: boolean) => {
        if (!abierta && paso === pasoActual) {
            setPaso(null);
        }
    };

    return (
        <>
            <BotonAtajo
                icono={Upload}
                titulo={corriendo ? 'Importando equipos...' : 'Importar'}
                cantidad={importacionesSinLeer ?? 0}
                cargando={corriendo}
                dataTest="importar-button"
                onClick={abrir}
            />
            <SubirCsvModal
                open={paso === 'archivo'}
                onOpenChange={cerrarSi('archivo')}
                onLeido={(leido) => {
                    setArchivo(leido);
                    setPaso('mapeo');
                }}
            />
            {archivo ? (
                <RelacionarColumnasModal
                    key={archivo.archivo}
                    archivo={archivo}
                    open={paso === 'mapeo'}
                    onOpenChange={cerrarSi('mapeo')}
                    onAtras={() => setPaso('archivo')}
                    onIniciada={(iniciada) => {
                        setArchivo(null);
                        setImportacion(iniciada);
                        setPaso('avance');

                        if (!importacionEnCurso(iniciada)) {
                            avisarResultado(iniciada);
                        }
                    }}
                />
            ) : null}
            {importacion ? (
                <AvanceImportacionModal
                    importacion={importacion}
                    open={paso === 'avance'}
                    onOpenChange={cerrarSi('avance')}
                    onNueva={() => setPaso('archivo')}
                />
            ) : null}
        </>
    );
}
