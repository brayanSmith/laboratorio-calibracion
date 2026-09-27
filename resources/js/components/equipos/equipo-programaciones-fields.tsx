import { Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';
import EquipoProgramacionFields from '@/components/equipos/equipo-programacion-fields';
import { Button } from '@/components/ui/button';

type Props = {
    errors: Partial<Record<string, string>>;
};

/**
 * Repeatable programación de servicio rows for an equipo that does not exist
 * yet. Each row keeps a stable id so removing one from the middle does not
 * reset the values already typed into the others.
 */
export default function EquipoProgramacionesFields({ errors }: Props) {
    const [rowIds, setRowIds] = useState<number[]>([]);
    const nextId = useRef(0);

    const addRow = () => {
        setRowIds((ids) => [...ids, nextId.current++]);
    };

    const removeRow = (id: number) => {
        setRowIds((ids) => ids.filter((rowId) => rowId !== id));
    };

    return (
        <div className="space-y-3">
            {rowIds.map((id, index) => (
                <div
                    key={id}
                    className="space-y-3 rounded-lg border p-3"
                    data-test="equipo-programacion-row"
                >
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">
                            Programación {index + 1}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeRow(id)}
                            aria-label="Quitar programación"
                            data-test="equipo-programacion-row-remove"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>

                    <EquipoProgramacionFields
                        errors={errors}
                        fieldName={(key) => `programaciones[${index}][${key}]`}
                        fieldError={(key) =>
                            errors[`programaciones.${index}.${key}`]
                        }
                    />
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addRow}
                data-test="equipo-programacion-row-add"
            >
                <Plus className="h-4 w-4" /> Agregar programación
            </Button>
        </div>
    );
}
