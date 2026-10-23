import { useHttp } from '@inertiajs/react';
import { Eye, Mail, MailOpen, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { historial } from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import CargandoContenido from '@/components/cargando-contenido';
import AvanceImportacionModal, {
    BarraAvanceImportacion,
    importacionEnCurso,
} from '@/components/importaciones/avance-importacion-modal';
import ResultadoImportacion from '@/components/importaciones/resultado-importacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useMarcarImportacionLeida } from '@/hooks/use-marcar-importacion-leida';
import { cn } from '@/lib/utils';
import type { ResumenImportacion } from '@/types';

/** Cada cuánto se refresca el avance de las importaciones que siguen cargando. */
const INTERVALO_AVANCE_MS = 2000;

function formatearFecha(fecha: string | null): string {
    return fecha
        ? new Date(fecha).toLocaleString('es-CO', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '';
}

function TarjetaImportacion({
    importacion,
    onVerDetalle,
    onCambiarLeida,
}: {
    importacion: ResumenImportacion;
    onVerDetalle: () => void;
    onCambiarLeida: (leida: boolean) => Promise<void>;
}) {
    const [guardando, setGuardando] = useState(false);
    const enCurso = importacionEnCurso(importacion);
    const sinLeer = !enCurso && !importacion.leida;

    const cambiarLeida = () => {
        setGuardando(true);
        void onCambiarLeida(!importacion.leida).finally(() =>
            setGuardando(false),
        );
    };

    return (
        <div
            className={cn(
                'space-y-2 rounded-lg border p-3',
                sinLeer && 'border-primary/40 bg-primary/5',
            )}
            data-test="historial-importacion"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="flex items-center gap-2 truncate text-sm font-medium">
                        {sinLeer ? (
                            <span
                                className="size-2 shrink-0 rounded-full bg-primary"
                                aria-hidden="true"
                            />
                        ) : null}
                        <span className="truncate">
                            {importacion.nombre_original}
                        </span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {importacion.tipo_etiqueta} ·{' '}
                        {formatearFecha(importacion.creada_en)}
                    </p>
                </div>
                {sinLeer ? (
                    <Badge data-test="historial-sin-leer">Sin leer</Badge>
                ) : null}
            </div>

            {enCurso ? (
                <div className="space-y-2" data-test="historial-en-curso">
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        {importacion.estado === 'PENDIENTE'
                            ? 'En cola...'
                            : `Importando ${importacion.filas_procesadas} de ${importacion.total_filas} filas...`}
                    </p>
                    <BarraAvanceImportacion importacion={importacion} />
                </div>
            ) : (
                <ResultadoImportacion importacion={importacion} />
            )}

            {!enCurso ? (
                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={cambiarLeida}
                        loading={guardando}
                        data-test="historial-cambiar-leida"
                    >
                        {!guardando &&
                            (importacion.leida ? <Mail /> : <MailOpen />)}
                        {importacion.leida
                            ? 'Marcar como no leída'
                            : 'Marcar como leída'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onVerDetalle}
                        data-test="historial-ver-detalle"
                    >
                        <Eye />
                        Ver detalle
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

/**
 * Últimas importaciones del usuario: las que siguen cargando (con su avance, que se
 * refresca solo) y luego las sin leer, para revisarlas aunque el aviso
 * de "terminada" ya se haya cerrado. "Ver detalle" abre las filas con error y la marca como
 * leída; también se puede marcar como leída o no leída para llevar el seguimiento.
 */
export default function HistorialImportaciones() {
    const [importaciones, setImportaciones] = useState<
        ResumenImportacion[] | null
    >(null);
    const [detalleId, setDetalleId] = useState<number | null>(null);
    const [actualizando, setActualizando] = useState(false);
    const marcarLeida = useMarcarImportacionLeida();
    const { get } = useHttp<
        Record<string, never>,
        { importaciones: ResumenImportacion[] }
    >();

    const cargar = () =>
        get(historial().url)
            .then((respuesta) => setImportaciones(respuesta.importaciones))
            .catch(() => setImportaciones((actuales) => actuales ?? []));

    useEffect(() => {
        void cargar();
        // Solo al abrir la pestaña.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /** Vuelve a pedir la lista a mano, por si un resultado aún no aparece. */
    const actualizar = () => {
        setActualizando(true);
        void cargar().finally(() => setActualizando(false));
    };

    const hayEnCurso = importaciones?.some(importacionEnCurso) ?? false;

    // Mientras alguna siga cargando, se refresca su avance (y su resultado al terminar).
    useEffect(() => {
        if (!hayEnCurso) {
            return;
        }

        const intervalo = window.setInterval(
            () => void cargar(),
            INTERVALO_AVANCE_MS,
        );

        return () => window.clearInterval(intervalo);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hayEnCurso]);

    const cambiarLeida = async (
        importacion: ResumenImportacion,
        leida: boolean,
    ): Promise<void> => {
        const actualizada = await marcarLeida(importacion, leida);

        if (actualizada) {
            setImportaciones(
                (actuales) =>
                    actuales?.map((item) =>
                        item.id === actualizada.id ? actualizada : item,
                    ) ?? null,
            );
        }
    };

    const verDetalle = (importacion: ResumenImportacion) => {
        setDetalleId(importacion.id);

        if (!importacion.leida) {
            void cambiarLeida(importacion, true);
        }
    };

    if (importaciones === null) {
        return <CargandoContenido mensaje="Cargando importaciones..." />;
    }

    const botonActualizar = (
        <div className="flex justify-end">
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={actualizar}
                loading={actualizando}
                data-test="historial-actualizar"
            >
                {!actualizando && <RefreshCw />}
                Actualizar
            </Button>
        </div>
    );

    if (importaciones.length === 0) {
        return (
            <>
                {botonActualizar}
                <p className="py-8 text-center text-sm text-muted-foreground">
                    Todavía no has importado datos.
                </p>
            </>
        );
    }

    const detalle =
        importaciones.find((importacion) => importacion.id === detalleId) ??
        null;

    return (
        <>
            {botonActualizar}
            <div
                className="max-h-[50vh] space-y-2 overflow-y-auto"
                data-test="historial-importaciones"
            >
                {importaciones.map((importacion) => (
                    <TarjetaImportacion
                        key={importacion.id}
                        importacion={importacion}
                        onVerDetalle={() => verDetalle(importacion)}
                        onCambiarLeida={(leida) =>
                            cambiarLeida(importacion, leida)
                        }
                    />
                ))}
            </div>

            {detalle ? (
                <AvanceImportacionModal
                    importacion={detalle}
                    open={detalleId !== null}
                    onOpenChange={(abierta) => {
                        if (!abierta) {
                            setDetalleId(null);
                        }
                    }}
                    acciones={
                        detalle.leida ? (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    void cambiarLeida(detalle, false);
                                    setDetalleId(null);
                                }}
                                data-test="detalle-marcar-no-leida"
                            >
                                <Mail />
                                Marcar como no leída
                            </Button>
                        ) : null
                    }
                />
            ) : null}
        </>
    );
}
