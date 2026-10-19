import { CircleAlert, CircleCheck } from 'lucide-react';
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
    onNueva: () => void;
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
}: Props) {
    const enCurso = importacionEnCurso(importacion);
    const porcentaje =
        importacion.total_filas > 0
            ? Math.round(
                  (importacion.filas_procesadas / importacion.total_filas) *
                      100,
              )
            : 0;
    const erroresSinDetalle =
        importacion.filas_con_error - importacion.errores.length;

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
                ) : importacion.estado === 'FALLIDA' ? (
                    <p
                        className="flex items-center gap-2 text-sm text-destructive"
                        data-test="importacion-fallida"
                    >
                        <CircleAlert className="size-4" />
                        La importación se detuvo por un error inesperado. Los
                        equipos importados hasta ese momento se conservan.
                    </p>
                ) : (
                    <div
                        className="space-y-2"
                        data-test="importacion-resultado"
                    >
                        <p className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            <CircleCheck className="size-4" />
                            {importacion.filas_creadas} equipos importados
                        </p>
                        {importacion.filas_con_error > 0 ? (
                            <p className="flex items-center gap-2 text-sm font-medium text-destructive">
                                <CircleAlert className="size-4" />
                                {importacion.filas_con_error} filas no se
                                importaron
                            </p>
                        ) : null}
                    </div>
                )}

                {importacion.errores.length > 0 ? (
                    <div className="space-y-1">
                        <p className="text-sm font-medium">Filas con errores</p>
                        <ul
                            className="max-h-64 divide-y overflow-y-auto rounded-lg border text-sm"
                            data-test="importacion-errores"
                        >
                            {importacion.errores.map((error) => (
                                <li
                                    key={error.fila}
                                    className="flex gap-3 px-3 py-2"
                                >
                                    <span className="shrink-0 font-medium tabular-nums">
                                        Fila {error.fila}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {error.mensaje}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {erroresSinDetalle > 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Y {erroresSinDetalle} filas más con errores.
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cerrar</Button>
                    </DialogClose>
                    {!enCurso ? (
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
