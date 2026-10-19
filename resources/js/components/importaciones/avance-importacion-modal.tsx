import type { ReactNode } from 'react';
import ErroresImportacion from '@/components/importaciones/errores-importacion';
import ResultadoImportacion from '@/components/importaciones/resultado-importacion';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { ResumenImportacion } from '@/types';

type Props = {
    importacion: ResumenImportacion;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Muestra el botón "Nueva importación" al terminar. */
    onNueva?: () => void;
    /** Botones extra del pie (ej. marcar como no leída en el detalle). */
    acciones?: ReactNode;
};

export function importacionEnCurso(importacion: ResumenImportacion): boolean {
    return (
        importacion.estado === 'PENDIENTE' ||
        importacion.estado === 'PROCESANDO'
    );
}

/** Avance de la importación mientras corre en segundo plano, y su resultado al terminar. */
export default function AvanceImportacionModal({
    importacion,
    open,
    onOpenChange,
    onNueva,
    acciones,
}: Props) {
    const enCurso = importacionEnCurso(importacion);
    const porcentaje =
        importacion.total_filas > 0
            ? Math.round(
                  (importacion.filas_procesadas / importacion.total_filas) *
                      100,
              )
            : 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Importación de equipos</DialogTitle>
                    <DialogDescription>
                        {importacion.nombre_original}
                    </DialogDescription>
                </DialogHeader>

                {enCurso ? (
                    <div className="space-y-3" data-test="importacion-avance">
                        <p className="flex items-center gap-2 text-sm">
                            <Spinner />
                            {importacion.estado === 'PENDIENTE'
                                ? 'En cola, comenzará en un momento...'
                                : `Importando ${importacion.filas_procesadas} de ${importacion.total_filas} filas...`}
                        </p>
                        <div
                            className="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={porcentaje}
                        >
                            <div
                                className="h-full bg-primary transition-[width] duration-500"
                                style={{ width: `${porcentaje}%` }}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Puedes cerrar esta ventana y seguir trabajando: la
                            importación continúa en segundo plano y te
                            avisaremos al terminar.
                        </p>
                    </div>
                ) : (
                    <ResultadoImportacion importacion={importacion} />
                )}

                <ErroresImportacion importacion={importacion} />

                <DialogFooter className="gap-2">
                    {acciones}
                    <DialogClose asChild>
                        <Button variant="secondary">Cerrar</Button>
                    </DialogClose>
                    {!enCurso && onNueva ? (
                        <Button
                            type="button"
                            onClick={onNueva}
                            data-test="importacion-nueva"
                        >
                            Nueva importación
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
