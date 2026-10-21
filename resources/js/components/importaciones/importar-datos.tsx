import { router, useHttp, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    enCurso,
    show,
} from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import BotonAtajo from '@/components/boton-atajo';
import AvanceImportacionModal, {
    importacionEnCurso,
} from '@/components/importaciones/avance-importacion-modal';
import RelacionarColumnasModal from '@/components/importaciones/relacionar-columnas-modal';
import SubirCsvModal from '@/components/importaciones/subir-csv-modal';
import { useMarcarImportacionLeida } from '@/hooks/use-marcar-importacion-leida';
import { tiposImportacion } from '@/lib/tipos-importacion';
import type { ArchivoImportacion, ResumenImportacion } from '@/types';

/** Cada cuánto se consulta el avance de las importaciones en curso. */
const INTERVALO_AVANCE_MS = 2000;

type Paso = 'archivo' | 'mapeo' | 'avance';

/**
 * Botón "Importar" del menú superior: sube un CSV del tipo elegido (equipos, ítems,
 * clientes...), relaciona sus columnas con los campos y lo importa en segundo plano.
 * Pueden correr varias importaciones a la vez: el botón siempre abre "Subir archivo",
 * muestra un spinner mientras alguna sigue en curso y avisa al terminar cada una, aunque
 * el usuario haya cerrado la ventana. El globito cuenta las importaciones terminadas cuyo
 * resultado el usuario aún no ha revisado.
 */
export default function ImportarDatos() {
    const { importacionesSinLeer } = usePage().props;
    const [paso, setPaso] = useState<Paso | null>(null);
    const marcarLeida = useMarcarImportacionLeida();
    const [archivo, setArchivo] = useState<ArchivoImportacion | null>(null);
    /** Importaciones en curso que se siguen para avisar cuando terminen. */
    const [enProceso, setEnProceso] = useState<ResumenImportacion[]>([]);
    /** La que se muestra en la ventana de avance (la última iniciada o la de un aviso). */
    const [vista, setVista] = useState<ResumenImportacion | null>(null);
    // Los avisos se arman dentro del intervalo, que no ve el estado actualizado.
    const pasoActual = useRef<Paso | null>(null);
    const vistaActual = useRef<ResumenImportacion | null>(null);
    /** Las ya avisadas: dos consultas cruzadas no deben repetir el aviso. */
    const avisadas = useRef(new Set<number>());

    useEffect(() => {
        pasoActual.current = paso;
        vistaActual.current = vista;
    }, [paso, vista]);

    const { get: consultarEnCurso } = useHttp<
        Record<string, never>,
        { importaciones: ResumenImportacion[] }
    >();
    const { get: consultar } = useHttp<
        Record<string, never>,
        ResumenImportacion
    >();

    // Retoma las importaciones en curso al recargar la página o cambiar de pestaña.
    useEffect(() => {
        consultarEnCurso(enCurso().url)
            .then((respuesta) => setEnProceso(respuesta?.importaciones ?? []))
            .catch(() => undefined);
        // Solo al montar el menú.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const idsEnProceso = enProceso.map((importacion) => importacion.id).join();

    useEffect(() => {
        if (idsEnProceso === '') {
            return;
        }

        const intervalo = window.setInterval(() => {
            enProceso.forEach((importacion) => {
                consultar(show(importacion.id).url)
                    .then((actual) => {
                        if (actual) {
                            actualizar(actual);
                        }
                    })
                    .catch(() => undefined);
            });
        }, INTERVALO_AVANCE_MS);

        return () => window.clearInterval(intervalo);
        // Se reinicia solo cuando cambia el conjunto de importaciones en curso.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [idsEnProceso]);

    /** Guarda el avance de una importación y, si ya terminó, deja de seguirla y avisa. */
    const actualizar = (actual: ResumenImportacion) => {
        if (vistaActual.current?.id === actual.id) {
            setVista(actual);
        }

        if (importacionEnCurso(actual)) {
            setEnProceso((actuales) =>
                actuales.map((importacion) =>
                    importacion.id === actual.id ? actual : importacion,
                ),
            );

            return;
        }

        setEnProceso((actuales) =>
            actuales.filter((importacion) => importacion.id !== actual.id),
        );
        avisarResultado(actual);
    };

    const marcarComoLeida = (resultado: ResumenImportacion) => {
        void marcarLeida(resultado, true).then((actualizada) => {
            if (actualizada && vistaActual.current?.id === actualizada.id) {
                setVista(actualizada);
            }
        });
    };

    const verResultado = (resultado: ResumenImportacion) => {
        setVista(resultado);
        setPaso('avance');
        marcarComoLeida(resultado);
    };

    const avisarResultado = (resultado: ResumenImportacion) => {
        if (avisadas.current.has(resultado.id)) {
            return;
        }

        avisadas.current.add(resultado.id);
        const tipo = resultado.tipo_etiqueta.toLowerCase();

        // Si la está mirando en la ventana de avance, ya vio el resultado.
        if (
            pasoActual.current === 'avance' &&
            vistaActual.current?.id === resultado.id
        ) {
            marcarComoLeida(resultado);
        } else {
            router.reload({ only: ['importacionesSinLeer'], async: true });
        }

        const verDetalle = {
            label: 'Ver detalle',
            onClick: () => verResultado(resultado),
        };

        if (resultado.estado === 'FALLIDA') {
            toast.error(`La importación de ${tipo} falló.`, {
                action: verDetalle,
            });
        } else if (resultado.filas_con_error > 0) {
            toast.warning(
                `Importación de ${tipo} terminada: ${resultado.filas_creadas} registros importados y ${resultado.filas_con_error} filas con errores.`,
                { action: verDetalle, duration: 10000 },
            );
        } else {
            toast.success(
                `Importación de ${tipo} terminada: ${resultado.filas_creadas} registros importados.`,
                { action: verDetalle },
            );
        }

        // Si el usuario está viendo el listado de lo importado, se actualiza con los nuevos.
        const ruta = tiposImportacion.find(
            (opcion) => opcion.value === resultado.tipo,
        )?.ruta;

        if (ruta && window.location.pathname.startsWith(ruta)) {
            router.reload();
        }
    };

    const cerrarSi = (pasoActual: Paso) => (abierta: boolean) => {
        if (!abierta && paso === pasoActual) {
            setPaso(null);
        }
    };

    const titulo =
        enProceso.length === 0
            ? 'Importar'
            : enProceso.length === 1
              ? `Importando ${enProceso[0].tipo_etiqueta.toLowerCase()}...`
              : `${enProceso.length} importaciones en curso...`;

    return (
        <>
            <BotonAtajo
                icono={Upload}
                titulo={titulo}
                cantidad={importacionesSinLeer ?? 0}
                cargando={enProceso.length > 0}
                dataTest="importar-button"
                onClick={() => setPaso('archivo')}
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
                        setVista(iniciada);
                        vistaActual.current = iniciada;
                        setPaso('avance');
                        pasoActual.current = 'avance';

                        if (importacionEnCurso(iniciada)) {
                            setEnProceso((actuales) => [...actuales, iniciada]);
                        } else {
                            avisarResultado(iniciada);
                        }
                    }}
                />
            ) : null}
            {vista ? (
                <AvanceImportacionModal
                    importacion={vista}
                    open={paso === 'avance'}
                    onOpenChange={cerrarSi('avance')}
                    onNueva={() => setPaso('archivo')}
                />
            ) : null}
        </>
    );
}
