import { useState } from 'react';
import ColumnResizeHandle from '@/components/column-resize-handle';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import type { CalibracionDetalleMedicion } from '@/types';

type Props = {
    detalles: CalibracionDetalleMedicion[];
    errors: Partial<Record<string, string>>;
};

const inputClassName =
    'h-7 w-full rounded-none border-0 bg-transparent p-1 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset';

/** Error encontrado = valor de referencia - valor del instrumento. Null si el valor del
 * instrumento todavía no se llenó. */
function calcularErrorEncontrado(
    valorReferencia: string,
    valorInstrumento: string,
): number | null {
    const instrumento = parseFloat(valorInstrumento);

    if (!Number.isFinite(instrumento)) {
        return null;
    }

    return parseFloat(valorReferencia) - instrumento;
}

/** Resultado: APROBADO si el error encontrado no supera el EMP (el margen de error
 * permitido del equipo), NO_APROBADO si lo supera. Null si todavía no hay error
 * encontrado. */
function calcularResultado(
    errorEncontrado: number | null,
    emp: string,
): 'APROBADO' | 'NO_APROBADO' | null {
    if (errorEncontrado === null) {
        return null;
    }

    return errorEncontrado <= parseFloat(emp) ? 'APROBADO' : 'NO_APROBADO';
}

const etiquetaResultado: Record<'APROBADO' | 'NO_APROBADO', string> = {
    APROBADO: 'Aprobado',
    NO_APROBADO: 'No aprobado',
};

const colorResultado: Record<'APROBADO' | 'NO_APROBADO', string> = {
    APROBADO: '!border-transparent !bg-emerald-500 !text-white',
    NO_APROBADO: '!border-transparent !bg-red-500 !text-white',
};

/** Resultados de la calibración: una fila por cada detalle_medicion_alcance del equipo,
 * creadas al agendar (ver OrdenTrabajoController::crearDetallesMedicionCalibracion()). No
 * se agregan ni quitan filas aquí. Valor instrumento es el único campo editable; error
 * encontrado y resultado se calculan en vivo a partir de él (y se recalculan en el
 * servidor al guardar, ver CalibracionController::update()). Se edita como tabla dentro
 * de la modal; se guarda junto con el resto de la calibración al presionar el botón
 * "Guardar". Las columnas se pueden redimensionar arrastrando su separador, al estilo
 * Excel, y la tabla tiene su propio scroll horizontal. */
export default function CalibracionResultados({ detalles, errors }: Props) {
    const [valoresInstrumento, setValoresInstrumento] = useState<
        Record<number, string>
    >(() =>
        Object.fromEntries(
            detalles.map((detalle) => [
                detalle.id,
                detalle.valor_instrumento ?? '',
            ]),
        ),
    );
    const { anchos, iniciarRedimension } = useResizableColumns({
        referencia: 130,
        instrumento: 110,
        error: 100,
        emp: 80,
        incertidumbre: 100,
        resultado: 110,
    });

    if (detalles.length === 0) {
        return null;
    }

    const anchoTotal = Object.values(anchos).reduce((a, b) => a + b, 0);

    return (
        <div className="min-w-0 space-y-2">
            <Label>Resultados de la calibración</Label>

            <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
                <table
                    className="text-sm"
                    style={{ width: anchoTotal, tableLayout: 'fixed' }}
                >
                    <colgroup>
                        <col style={{ width: anchos.referencia }} />
                        <col style={{ width: anchos.instrumento }} />
                        <col style={{ width: anchos.error }} />
                        <col style={{ width: anchos.emp }} />
                        <col style={{ width: anchos.incertidumbre }} />
                        <col style={{ width: anchos.resultado }} />
                    </colgroup>
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="relative px-3 py-2 font-medium">
                                Valor referencia
                                <ColumnResizeHandle
                                    label="Valor referencia"
                                    onPointerDown={iniciarRedimension(
                                        'referencia',
                                    )}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Valor instrumento
                                <ColumnResizeHandle
                                    label="Valor instrumento"
                                    onPointerDown={iniciarRedimension(
                                        'instrumento',
                                    )}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Error encontrado
                                <ColumnResizeHandle
                                    label="Error encontrado"
                                    onPointerDown={iniciarRedimension('error')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                EMP
                                <ColumnResizeHandle
                                    label="EMP"
                                    onPointerDown={iniciarRedimension('emp')}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Incertidumbre
                                <ColumnResizeHandle
                                    label="Incertidumbre"
                                    onPointerDown={iniciarRedimension(
                                        'incertidumbre',
                                    )}
                                />
                            </th>
                            <th className="px-3 py-2 font-medium">Resultado</th>
                        </tr>
                    </thead>
                    <tbody
                        className="divide-y"
                        data-test="calibracion-resultados-list"
                    >
                        {detalles.map((detalle, index) => {
                            const valorInstrumento =
                                valoresInstrumento[detalle.id];
                            const errorEncontrado = calcularErrorEncontrado(
                                detalle.valor_referencia,
                                valorInstrumento,
                            );
                            const resultado = calcularResultado(
                                errorEncontrado,
                                detalle.emp,
                            );

                            return (
                                <tr
                                    key={detalle.id}
                                    data-test="calibracion-resultado-row"
                                >
                                    <td className="truncate px-3 py-2">
                                        {detalle.valor_referencia}{' '}
                                        {detalle.unidad_simbolo}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={valorInstrumento}
                                            onChange={(event) =>
                                                setValoresInstrumento(
                                                    (prev) => ({
                                                        ...prev,
                                                        [detalle.id]:
                                                            event.target.value,
                                                    }),
                                                )
                                            }
                                            className={inputClassName}
                                        />
                                        <input
                                            type="hidden"
                                            name={`detalles[${index}][id]`}
                                            value={detalle.id}
                                        />
                                        <input
                                            type="hidden"
                                            name={`detalles[${index}][valor_instrumento]`}
                                            value={valorInstrumento}
                                        />
                                    </td>
                                    <td
                                        className="truncate px-3 py-2 text-muted-foreground"
                                        data-test="calibracion-error-encontrado"
                                    >
                                        {errorEncontrado !== null
                                            ? errorEncontrado.toFixed(2)
                                            : '—'}
                                    </td>
                                    <td className="truncate px-3 py-2">
                                        {detalle.emp}
                                    </td>
                                    <td className="truncate px-3 py-2">
                                        {detalle.incertidumbre}
                                    </td>
                                    <td
                                        className="truncate px-3 py-2"
                                        data-test="calibracion-resultado"
                                    >
                                        {resultado ? (
                                            <Badge
                                                className={
                                                    colorResultado[resultado]
                                                }
                                            >
                                                {etiquetaResultado[resultado]}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {detalles.map((detalle, index) => {
                const mensaje = errors[`detalles.${index}.valor_instrumento`];

                return mensaje ? (
                    <p key={detalle.id} className="text-sm text-destructive">
                        {mensaje}
                    </p>
                ) : null;
            })}
        </div>
    );
}
