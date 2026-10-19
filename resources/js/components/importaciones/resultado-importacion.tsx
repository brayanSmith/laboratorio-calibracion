import { CircleAlert, CircleCheck } from 'lucide-react';
import type { ResumenImportacion } from '@/types';

type Props = {
    importacion: ResumenImportacion;
};

/** Resultado de una importación terminada: cuántos equipos entraron y cuántas filas no. */
export default function ResultadoImportacion({ importacion }: Props) {
    if (importacion.estado === 'FALLIDA') {
        return (
            <p
                className="flex items-center gap-2 text-sm text-destructive"
                data-test="importacion-fallida"
            >
                <CircleAlert className="size-4 shrink-0" />
                La importación se detuvo por un error inesperado. Los equipos
                importados hasta ese momento se conservan.
            </p>
        );
    }

    return (
        <div className="space-y-1" data-test="importacion-resultado">
            <p className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                <CircleCheck className="size-4 shrink-0" />
                {importacion.filas_creadas} equipos importados
            </p>
            {importacion.filas_con_error > 0 ? (
                <p className="flex items-center gap-2 text-sm font-medium text-destructive">
                    <CircleAlert className="size-4 shrink-0" />
                    {importacion.filas_con_error} filas no se importaron
                </p>
            ) : null}
        </div>
    );
}
