import { useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    adjuntos,
    store,
} from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MOTIVO_SIN_CONEXION, motivoErrorHttp } from '@/lib/motivo-error-http';
import type {
    ArchivoImportacion,
    ResumenImportacion,
    TipoImportacion,
} from '@/types';

/** Valor del select para un campo que no se toma de ninguna columna. */
const SIN_COLUMNA = 'ninguna';

type Props = {
    archivo: ArchivoImportacion;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAtras: () => void;
    onIniciada: (importacion: ResumenImportacion) => void;
};

type DatosImportacion = {
    tipo: TipoImportacion;
    archivo: string;
    nombre_original: string;
    mapeo: Record<string, number | null>;
};

type DatosAdjuntos = {
    tipo: TipoImportacion;
    lote: string;
    adjuntos: File[];
};

/** Archivos de la carpeta elegida que se van a subir, y cuántos se dejaron fuera. */
type CarpetaAdjuntos = {
    nombre: string;
    archivos: File[];
    omitidos: number;
};

/** Identificador del lote de adjuntos: agrupa las tandas en que se sube la carpeta. */
function nuevoLote(): string {
    const caracteres =
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    return Array.from(crypto.getRandomValues(new Uint8Array(40)))
        .map((valor) => caracteres[valor % caracteres.length])
        .join('');
}

/**
 * Segundo paso: relacionar cada campo del tipo elegido con una columna del CSV, porque los
 * encabezados pueden cambiar de nombre u orden entre archivos. Arranca con las columnas
 * cuyos encabezados se parecen al campo.
 */
export default function RelacionarColumnasModal({
    archivo,
    open,
    onOpenChange,
    onAtras,
    onIniciada,
}: Props) {
    const { data, setData, post, processing, errors, transform } = useHttp<
        DatosImportacion,
        ResumenImportacion
    >({
        tipo: archivo.tipo,
        archivo: archivo.archivo,
        nombre_original: archivo.nombre_original,
        mapeo: archivo.sugerencias,
    });
    const subida = useHttp<DatosAdjuntos, { subidos: number }>({
        tipo: archivo.tipo,
        lote: '',
        adjuntos: [],
    });
    const [carpeta, setCarpeta] = useState<CarpetaAdjuntos | null>(null);
    const [subidos, setSubidos] = useState<number | null>(null);
    const selectorCarpeta = useRef<HTMLInputElement>(null);

    // "webkitdirectory" (elegir una carpeta entera) no está en los tipos de React.
    useEffect(() => {
        selectorCarpeta.current?.setAttribute('webkitdirectory', '');
    }, [archivo.adjuntos]);

    const errores = errors as Partial<Record<string, string>>;
    const primeraFila = archivo.vista_previa[0] ?? [];

    const elegirCarpeta = (lista: FileList | null) => {
        const configuracion = archivo.adjuntos;

        if (!configuracion || !lista || lista.length === 0) {
            setCarpeta(null);

            return;
        }

        const todos = Array.from(lista);
        const archivos = todos.filter(
            (archivoCarpeta) =>
                configuracion.extensiones.includes(
                    archivoCarpeta.name.split('.').pop()?.toLowerCase() ?? '',
                ) &&
                archivoCarpeta.size <= configuracion.tamanio_maximo_kb * 1024,
        );

        setCarpeta({
            nombre: todos[0].webkitRelativePath.split('/')[0] || 'Carpeta',
            archivos,
            omitidos: todos.length - archivos.length,
        });
    };

    /**
     * Sube los adjuntos en tandas (PHP acepta pocos archivos por petición) y devuelve el lote.
     * Se detiene en la primera tanda que falle y devuelve el motivo.
     */
    const subirAdjuntos = async (): Promise<
        { lote: string | null } | { motivo: string }
    > => {
        if (!archivo.adjuntos || !carpeta || carpeta.archivos.length === 0) {
            return { lote: null };
        }

        const lote = nuevoLote();
        const porTanda = archivo.adjuntos.archivos_por_tanda;
        // Un error de validación no rechaza la promesa: llega por onError.
        let motivo: string | null = null;

        setSubidos(0);

        for (
            let inicio = 0;
            inicio < carpeta.archivos.length;
            inicio += porTanda
        ) {
            const tanda = carpeta.archivos.slice(inicio, inicio + porTanda);

            subida.transform(() => ({
                tipo: archivo.tipo,
                lote,
                adjuntos: tanda,
            }));
            await subida
                .post(adjuntos().url, {
                    onError: (erroresTanda) => {
                        motivo = Object.values(erroresTanda)[0] ?? null;
                    },
                    onHttpException: (respuesta) => {
                        motivo = motivoErrorHttp(respuesta);
                    },
                    onNetworkError: () => {
                        motivo = MOTIVO_SIN_CONEXION;
                    },
                })
                .catch(() => undefined);

            if (motivo !== null) {
                return { motivo };
            }

            setSubidos(inicio + tanda.length);
        }

        return { lote };
    };

    const importar = async () => {
        const resultado = await subirAdjuntos();

        if ('motivo' in resultado) {
            setSubidos(null);
            toast.error(
                `No se pudieron subir los adjuntos: ${resultado.motivo}`,
            );

            return;
        }

        transform((datos) => ({ ...datos, lote_adjuntos: resultado.lote }));
        post(store().url, {
            onSuccess: onIniciada,
            // Los errores del mapeo y del archivo se ven en la modal; el resto, en el aviso.
            onError: (erroresImportacion) => {
                const otro = Object.entries(erroresImportacion).find(
                    ([campo]) =>
                        campo !== 'archivo' && !campo.startsWith('mapeo.'),
                );

                if (otro) {
                    toast.error(
                        `No se pudo iniciar la importación: ${otro[1]}`,
                    );
                }
            },
            onHttpException: (respuesta) => {
                toast.error(
                    `No se pudo iniciar la importación: ${motivoErrorHttp(respuesta)}`,
                );
            },
            onNetworkError: () => {
                toast.error(
                    `No se pudo iniciar la importación: ${MOTIVO_SIN_CONEXION}`,
                );
            },
        })
            .catch(() => undefined)
            .finally(() => setSubidos(null));
    };

    const subiendo = subidos !== null && carpeta !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Relacionar columnas · {archivo.tipo_etiqueta}
                    </DialogTitle>
                    <DialogDescription>
                        Elige qué columna de{' '}
                        <strong>{archivo.nombre_original}</strong> corresponde a
                        cada campo. Los marcados con * son obligatorios.
                        {archivo.nota ? ` ${archivo.nota}` : null}
                    </DialogDescription>
                </DialogHeader>

                <InputError message={errores.archivo} />

                <div
                    className="divide-y rounded-lg border"
                    data-test="importar-mapeo"
                >
                    <div className="hidden grid-cols-[1fr_1fr_1fr] gap-3 bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground sm:grid">
                        <span>Campo</span>
                        <span>Columna del CSV</span>
                        <span>Ejemplo (primera fila)</span>
                    </div>
                    {archivo.campos.map((campo) => {
                        const indice = data.mapeo[campo.clave] ?? null;
                        const error = errores[`mapeo.${campo.clave}`];

                        return (
                            <div
                                key={campo.clave}
                                className="grid items-center gap-2 px-3 py-2 sm:grid-cols-[1fr_1fr_1fr] sm:gap-3"
                            >
                                <div className="text-sm">
                                    <label
                                        htmlFor={`mapeo-${campo.clave}`}
                                        className="font-medium"
                                    >
                                        {campo.etiqueta}
                                        {campo.requerido ? (
                                            <span className="text-destructive">
                                                {' '}
                                                *
                                            </span>
                                        ) : null}
                                    </label>
                                    {campo.ayuda ? (
                                        <p className="text-xs text-muted-foreground">
                                            {campo.ayuda}
                                        </p>
                                    ) : null}
                                </div>
                                <div>
                                    <Select
                                        value={
                                            indice === null
                                                ? SIN_COLUMNA
                                                : String(indice)
                                        }
                                        onValueChange={(valor) =>
                                            setData('mapeo', {
                                                ...data.mapeo,
                                                [campo.clave]:
                                                    valor === SIN_COLUMNA
                                                        ? null
                                                        : Number(valor),
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`mapeo-${campo.clave}`}
                                            className="w-full"
                                            aria-invalid={!!error}
                                            data-test={`mapeo-${campo.clave}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={SIN_COLUMNA}>
                                                — No importar —
                                            </SelectItem>
                                            {archivo.encabezados.map(
                                                (encabezado, posicion) => (
                                                    <SelectItem
                                                        key={posicion}
                                                        value={String(posicion)}
                                                    >
                                                        {encabezado}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={error} />
                                </div>
                                <span className="truncate text-sm text-muted-foreground">
                                    {indice === null
                                        ? '—'
                                        : primeraFila[indice] || '(vacío)'}
                                </span>
                            </div>
                        );
                    })}
                </div>

                {archivo.adjuntos ? (
                    <div
                        className="space-y-2 rounded-lg border p-3"
                        data-test="importar-adjuntos"
                    >
                        <div className="text-sm">
                            <label
                                htmlFor="importar-carpeta-adjuntos"
                                className="font-medium"
                            >
                                Carpeta de adjuntos
                            </label>
                            <p className="text-xs text-muted-foreground">
                                Elige la carpeta con los archivos que nombra la
                                columna Adjunto. Se emparejan por nombre (ej.
                                certificado-001.pdf). Se aceptan{' '}
                                {archivo.adjuntos.extensiones.join(', ')} de
                                hasta{' '}
                                {archivo.adjuntos.tamanio_maximo_kb / 1024} MB.
                            </p>
                        </div>
                        <input
                            ref={selectorCarpeta}
                            id="importar-carpeta-adjuntos"
                            type="file"
                            multiple
                            disabled={processing || subiendo}
                            onChange={(event) =>
                                elegirCarpeta(event.target.files)
                            }
                            className="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-1.5 file:text-sm"
                            data-test="importar-carpeta-adjuntos"
                        />
                        {carpeta ? (
                            <p
                                className="text-xs text-muted-foreground"
                                data-test="importar-carpeta-resumen"
                            >
                                <strong>{carpeta.nombre}</strong>:{' '}
                                {carpeta.archivos.length} archivo(s) para subir
                                {carpeta.omitidos > 0
                                    ? ` · ${carpeta.omitidos} omitido(s) por su tipo o tamaño`
                                    : ''}
                            </p>
                        ) : null}
                        {subiendo ? (
                            <p className="text-xs font-medium">
                                Subiendo adjuntos: {subidos} de{' '}
                                {carpeta.archivos.length}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onAtras}
                        disabled={processing || subiendo}
                    >
                        Atrás
                    </Button>
                    <Button
                        type="button"
                        onClick={importar}
                        loading={processing || subiendo}
                        data-test="importar-iniciar"
                    >
                        Importar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
