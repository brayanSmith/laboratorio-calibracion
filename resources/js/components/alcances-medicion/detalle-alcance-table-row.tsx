import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    AlcanceMedicionUnidadOpcion,
    DetalleAlcanceMedicion,
} from '@/types';

type Props = {
    index: number;
    detalle?: DetalleAlcanceMedicion;
    unidadesMedida: AlcanceMedicionUnidadOpcion[];
    errors: Partial<Record<string, string>>;
    onRemove: () => void;
};

const numericFields = [
    {
        field: 'valor_instrumento',
        label: 'Valor del instrumento',
        min: undefined,
    },
    { field: 'emp', label: 'EMP', min: '0' },
    { field: 'incertidumbre', label: 'Incertidumbre', min: '0' },
] as const;

/**
 * Fila editable de la tabla de detalles del formulario de nuevo alcance. Envía
 * sus valores como `detalles[index][campo]`.
 */
export default function DetalleAlcanceTableRow({
    index,
    detalle,
    unidadesMedida,
    errors,
    onRemove,
}: Props) {
    const [unidadMedidaId, setUnidadMedidaId] = useState(
        detalle ? String(detalle.unidad_medida_id) : '',
    );
    const errorOf = (field: string) => errors[`detalles.${index}.${field}`];

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
                    onValueChange={setUnidadMedidaId}
                    options={unidadesMedida.map((unidad) => ({
                        id: unidad.id,
                        label: `${unidad.nombre} (${unidad.simbolo})`,
                    }))}
                    placeholder="Unidad de medida"
                    searchPlaceholder="Buscar unidad..."
                    className="h-7 w-full rounded-none border-0 bg-transparent p-0 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                />
                <InputError message={errorOf('unidad_medida_id')} />
            </td>

            {numericFields.map(({ field, label, min }) => (
                <td key={field} className="px-3 py-2">
                    <Input
                        name={`detalles[${index}][${field}]`}
                        type="number"
                        step="0.01"
                        min={min}
                        defaultValue={detalle?.[field]}
                        aria-label={label}
                        className="h-7 w-full rounded-none border-0 bg-transparent p-0 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                        required
                    />
                    <InputError message={errorOf(field)} />
                </td>
            ))}

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
