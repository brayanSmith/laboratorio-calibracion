import ColumnResizeHandle from '@/components/column-resize-handle';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import ResultadoCalibracionDetalle from '@/components/calibraciones/resultado-calibracion-detalle';
import {
    calcularErrorEncontrado,
    calcularResultado,
    resultadoDeLaCalibracion,
} from '@/lib/calibracion-resultado';
import type { ValoresInstrumento } from '@/lib/calibracion-resultado';
import type { CalibracionDetalleMedicion } from '@/types';

type Props = {
    detalles: CalibracionDetalleMedicion[];
    /** Valor del instrumento escrito en cada fila; lo controla el formulario. */
    valoresInstrumento: ValoresInstrumento;
    onValorInstrumentoChange: (detalleId: number, valor: string) => void;
    errors: Partial<Record<string, string>>;
};

const inputClassName =
    'h-7 w-full rounded-none border-0 bg-transparent p-1 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset';

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
export default function CalibracionResultados({
    detalles,
    valoresInstrumento,
    onValorInstrumentoChange,
    errors,
}: Props) {
    const { anchos, iniciarRedimension } = useResizableColumns({
        referencia: 130,
        instrumento: 110,
        error: 100,
        emp: 80,
        empPorcentaje: 90,
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
                        <col style={{ width: anchos.empPorcentaje }} />
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
                                EMP %
                                <ColumnResizeHandle
                                    label="EMP %"
                                    onPointerDown={iniciarRedimension(
                                        'empPorcentaje',
                                    )}
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
                                valoresInstrumento[detalle.id] ?? '';
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
                                                onValorInstrumentoChange(
                                                    detalle.id,
                                                    event.target.value,
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
                                    <td
                                        className="truncate px-3 py-2"
                                        data-test="calibracion-emp-porcentaje"
                                    >
                                        {detalle.emp_porcentaje !== null
                                            ? `${(parseFloat(detalle.emp_porcentaje) * 100).toFixed(2)} %`
                                            : '—'}
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

            <ResultadoCalibracionDetalle
                resultado={resultadoDeLaCalibracion(
                    detalles,
                    valoresInstrumento,
                )}
            />

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
