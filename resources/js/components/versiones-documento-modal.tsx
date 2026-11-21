import { ExternalLink, FileText } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatearFechaHora } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import type { VersionDocumento } from '@/types';

type Props = {
    /** Ej. "Certificados de calibración" o "Informes de mantenimiento". */
    titulo: string;
    /** El equipo o la orden de trabajo, para saber de qué documento son. */
    descripcion: string;
    versiones: VersionDocumento[] | null;
    onClose: () => void;
};

/**
 * Las versiones emitidas del PDF de un mantenimiento o de una calibración: la original y
 * cada corrección, con quién y cuándo se generó, y la vista previa de la elegida (al
 * abrir, la más reciente).
 */
export default function VersionesDocumentoModal({
    titulo,
    descripcion,
    versiones,
    onClose,
}: Props) {
    return (
        <Dialog
            open={versiones !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {versiones ? (
                <DialogContent
                    className="flex h-[92dvh] max-h-[92dvh] flex-col gap-3 p-3 sm:max-w-6xl"
                    data-test="versiones-documento-modal"
                >
                    <ContenidoVersiones
                        key={versiones.map((version) => version.id).join('-')}
                        titulo={titulo}
                        descripcion={descripcion}
                        versiones={versiones}
                    />
                </DialogContent>
            ) : null}
        </Dialog>
    );
}

function ContenidoVersiones({
    titulo,
    descripcion,
    versiones,
}: {
    titulo: string;
    descripcion: string;
    versiones: VersionDocumento[];
}) {
    const recientePrimero = [...versiones].reverse();
    const [elegidaId, setElegidaId] = useState(recientePrimero[0]?.id);
    const elegida =
        recientePrimero.find((version) => version.id === elegidaId) ??
        recientePrimero[0];

    return (
        <>
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2 text-base">
                    <FileText className="size-4 text-muted-foreground" />
                    {titulo}
                </DialogTitle>
                <DialogDescription>
                    {descripcion} · {versiones.length}{' '}
                    {versiones.length === 1 ? 'versión' : 'versiones'}
                </DialogDescription>
            </DialogHeader>

            <div className="grid min-h-0 flex-1 grid-rows-[auto_minmax(0,1fr)] gap-3 md:grid-cols-[15rem_minmax(0,1fr)] md:grid-rows-1">
                <ul className="flex gap-2 overflow-x-auto md:flex-col md:overflow-y-auto">
                    {recientePrimero.map((version, indice) => (
                        <li key={version.id} className="shrink-0">
                            <button
                                type="button"
                                onClick={() => setElegidaId(version.id)}
                                className={cn(
                                    'w-full rounded-md border p-2.5 text-left text-sm transition-colors hover:bg-muted',
                                    version.id === elegida?.id &&
                                        'border-primary bg-primary/5 ring-1 ring-primary',
                                )}
                                data-test="version-documento"
                            >
                                <span className="flex items-center justify-between gap-2">
                                    <span className="font-mono font-medium">
                                        {version.codigo}
                                    </span>
                                    {indice === 0 ? (
                                        <Badge variant="secondary">
                                            Vigente
                                        </Badge>
                                    ) : null}
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    {indice === recientePrimero.length - 1
                                        ? 'Original'
                                        : 'Corrección'}{' '}
                                    · {formatearFechaHora(version.fecha)}
                                </span>
                                {version.usuario ? (
                                    <span className="block text-xs text-muted-foreground">
                                        {version.usuario}
                                    </span>
                                ) : null}
                            </button>
                        </li>
                    ))}
                </ul>

                {elegida ? (
                    <div className="flex min-h-0 flex-col gap-2">
                        <object
                            key={elegida.id}
                            data={elegida.url}
                            type="application/pdf"
                            className="min-h-0 w-full flex-1 rounded-md border bg-muted"
                            aria-label={elegida.codigo}
                        >
                            <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                                <FileText className="size-10 text-muted-foreground" />
                                <p className="max-w-xs text-sm text-muted-foreground">
                                    Tu navegador no puede mostrar el PDF aquí.
                                </p>
                            </div>
                        </object>
                        <div className="flex justify-end">
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={elegida.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> Abrir {elegida.codigo}
                                </a>
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
