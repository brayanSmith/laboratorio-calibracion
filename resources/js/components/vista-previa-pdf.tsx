import { ExternalLink, FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    /** PDF recién elegido en el campo de archivo (aún sin subir). */
    archivo: File | null;
    /** PDF ya guardado, que se muestra mientras no se elija otro. */
    urlActual?: string | null;
    className?: string;
};

/**
 * Muestra un PDF dentro de la modal con el visor del navegador, para leer sus datos (ej. el
 * número de certificado y la fecha) mientras se llenan los campos. El archivo elegido se ve
 * al instante, sin subirlo: se lee desde el equipo del usuario.
 */
export default function VistaPreviaPdf({
    archivo,
    urlActual = null,
    className,
}: Props) {
    const [urlArchivo, setUrlArchivo] = useState<string | null>(null);

    useEffect(() => {
        if (!archivo) {
            setUrlArchivo(null);

            return;
        }

        const url = URL.createObjectURL(archivo);
        setUrlArchivo(url);

        // Libera la copia en memoria del archivo al cambiarlo o cerrar la modal.
        return () => URL.revokeObjectURL(url);
    }, [archivo]);

    const url = urlArchivo ?? urlActual;

    return (
        <div
            className={cn(
                'flex min-h-80 flex-col overflow-hidden rounded-lg border bg-muted/30',
                className,
            )}
            data-test="vista-previa-pdf"
        >
            <div className="flex items-center justify-between gap-2 border-b bg-background px-3 py-2 text-sm">
                <span className="truncate font-medium">
                    {archivo
                        ? archivo.name
                        : url
                          ? 'Documento actual'
                          : 'Vista previa'}
                </span>
                {url ? (
                    <a
                        href={url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex shrink-0 items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <ExternalLink className="size-3.5" />
                        Abrir en otra pestaña
                    </a>
                ) : null}
            </div>

            {url ? (
                <iframe
                    key={url}
                    src={`${url}#view=FitH`}
                    title="Vista previa del PDF"
                    className="w-full flex-1 bg-background"
                />
            ) : (
                <div className="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-sm text-muted-foreground">
                    <FileText className="size-8" />
                    Selecciona el PDF del servicio para verlo aquí mientras
                    llenas el número de certificado y la fecha.
                </div>
            )}
        </div>
    );
}
