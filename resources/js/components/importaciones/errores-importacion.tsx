import type { ResumenImportacion } from '@/types';

type Props = {
    importacion: ResumenImportacion;
};

/** Filas que no se importaron, con su número de fila en el archivo y el motivo. */
export default function ErroresImportacion({ importacion }: Props) {
    if (importacion.errores.length === 0) {
        return null;
    }

    // Solo se guarda el detalle de los primeros errores; el resto solo se cuenta.
    const erroresSinDetalle =
        importacion.filas_con_error - importacion.errores.length;

    return (
        <div className="space-y-1">
            <p className="text-sm font-medium">Filas con errores</p>
            <ul
                className="max-h-64 divide-y overflow-y-auto rounded-lg border text-sm"
                data-test="importacion-errores"
            >
                {importacion.errores.map((error) => (
                    <li key={error.fila} className="flex gap-3 px-3 py-2">
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
    );
}
