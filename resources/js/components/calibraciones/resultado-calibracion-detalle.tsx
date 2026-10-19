import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import type { ResultadoMedicion } from '@/lib/calibracion-resultado';

type Props = {
    /** Null mientras falte completar alguna fila de resultados. */
    resultado: ResultadoMedicion | null;
    testId?: string;
};

const etiquetaResultado: Record<ResultadoMedicion, string> = {
    APROBADO: 'Aprobado',
    NO_APROBADO: 'No aprobado',
};

const colorResultado: Record<ResultadoMedicion, string> = {
    APROBADO: '!border-transparent !bg-emerald-500 !text-white',
    NO_APROBADO: '!border-transparent !bg-red-500 !text-white',
};

/** Resultado de la calibración como dato de solo lectura: se define solo a partir de los
 * resultados de la medición y se actualiza en vivo mientras se escriben. */
export default function ResultadoCalibracionDetalle({
    resultado,
    testId = 'resultado-calibracion-detalle',
}: Props) {
    return (
        <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">
                Resultado de la calibración
            </Label>
            <div>
                {resultado ? (
                    <Badge
                        className={`${colorResultado[resultado]} px-3 py-1 text-sm`}
                        data-test={testId}
                        data-resultado={resultado}
                    >
                        {etiquetaResultado[resultado]}
                    </Badge>
                ) : (
                    <Badge
                        variant="outline"
                        className="px-3 py-1 text-sm text-muted-foreground"
                        data-test={testId}
                        data-resultado="PENDIENTE"
                    >
                        Pendiente
                    </Badge>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                {resultado === null
                    ? 'Completa el valor del instrumento en todos los resultados para conocer el resultado.'
                    : 'Se define solo: aprobado si todos los resultados de la medición lo están.'}
            </p>
        </div>
    );
}
