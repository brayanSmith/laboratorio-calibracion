import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    AlcanceMedicionUnidadOpcion,
    DetalleAlcanceMedicion,
    EspecificacionAlcance,
} from '@/types';

type Props = {
    index: number;
    detalle?: DetalleAlcanceMedicion;
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
    especificacion: EspecificacionAlcance | null;
    errors: Partial<Record<string, string>>;
    onRemove: () => void;
};

const inputClassName =
    'h-7 w-full rounded-none border-0 bg-transparent p-0 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset';

/**
 * Separa la precisión del equipo ("±0.5" o "0.5%") en su símbolo y su número. El símbolo
 * decide si el EMP del detalle es un valor absoluto ("±", campo `emp`) o un porcentaje
 * ("%", campo `emp_porcentaje`).
 */
function parsePrecision(precision: string | null): {
    esPorcentaje: boolean;
    numero: string;
} {
    const esPorcentaje = precision?.endsWith('%') ?? false;

    return {
        esPorcentaje,
        numero: (precision ?? '').replace(/[±%]/g, ''),
    };
}

/**
 * Fila editable de la tabla de detalles del formulario de alcance. Envía sus
 * valores como `detalles[index][campo]`. El EMP parte de la precisión de los equipos
 * del alcance.
 */
export default function DetalleAlcanceTableRow({
    index,
    detalle,
    unidadesMedida,
    especificacion,
    errors,
    onRemove,
}: Props) {
    // Hasta que se elige otra, la unidad es la del detalle guardado o la de los equipos del alcance.
    const [unidadElegida, setUnidadElegida] = useState<string | null>(null);
    const unidadMedidaId =
        unidadElegida ??
        (detalle
            ? String(detalle.unidad_medida_id)
            : especificacion
              ? String(especificacion.unidad_medida_id)
              : '');
    const errorOf = (field: string) => errors[`detalles.${index}.${field}`];
    const { esPorcentaje, numero } = parsePrecision(especificacion?.precision ?? null);
    const empField = esPorcentaje ? 'emp_porcentaje' : 'emp';
    const empGuardado = detalle?.[empField];

    return (
        <tr className="align-top" data-test="alcance-medicion-detalle-row">
            <td className="px-3 py-2">
                {detalle ? (
                    <input
                        type="hidden"
                        name={`detalles[${index}][id]`}
                        value={detalle.id}
                    />
                ) : null}
                <Combobox
                    name={`detalles[${index}][unidad_medida_id]`}
                    value={unidadMedidaId}
                    onValueChange={setUnidadElegida}
                    options={unidadesMedida.map((unidad) => ({
                        id: unidad.id,
                        label: `${unidad.nombre} (${unidad.simbolo})`,
                    }))}
                    placeholder="Unidad de medida"
                    searchPlaceholder="Buscar unidad..."
                    className={inputClassName}
                />
                <InputError message={errorOf('unidad_medida_id')} />
            </td>

            <td className="px-3 py-2">
                <Input
                    name={`detalles[${index}][valor_referencia]`}
                    type="number"
                    step="0.01"
                    defaultValue={detalle?.valor_referencia}
                    aria-label="Valor de referencia"
                    className={inputClassName}
                    required
                />
                <InputError message={errorOf('valor_referencia')} />
            </td>

            <td className="px-3 py-2">
                <div className="flex items-center gap-1 text-xs">
                    {esPorcentaje ? null : <span>±</span>}
                    <Input
                        key={`${empField}-${numero}`}
                        name={`detalles[${index}][${empField}]`}
                        type="number"
                        step="0.01"
                        min="0"
                        max={esPorcentaje ? '100' : undefined}
                        defaultValue={empGuardado ?? numero}
                        aria-label={esPorcentaje ? 'EMP (%)' : 'EMP'}
                        className={inputClassName}
                        required
                    />
                    {esPorcentaje ? <span>%</span> : null}
                </div>
                <InputError message={errorOf(empField)} />
            </td>

            <td className="px-3 py-2">
                <Input
                    name={`detalles[${index}][incertidumbre]`}
                    type="number"
                    step="0.01"
                    min="0"
                    defaultValue={detalle?.incertidumbre}
                    aria-label="Incertidumbre"
                    className={inputClassName}
                    required
                />
                <InputError message={errorOf('incertidumbre')} />
            </td>

            <td className="px-3 py-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={onRemove}
                    aria-label="Quitar detalle"
                    data-test="alcance-medicion-remove-detalle"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </Button>
            </td>
        </tr>
    );
}
