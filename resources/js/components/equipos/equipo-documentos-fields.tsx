import { Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    errors: Partial<Record<string, string>>;
};

/**
 * Repeatable name+archivo rows for the documentos of an equipo that does not
 * exist yet. Each row keeps a stable id so removing one from the middle does
 * not reset the values the person already typed in the others.
 */
export default function EquipoDocumentosFields({ errors }: Props) {
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
                    className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                    data-test="equipo-documento-row"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`documento-nombre-${id}`}>Nombre</Label>
                        <Input
                            id={`documento-nombre-${id}`}
                            name={`documentos[${index}][nombre]`}
                            placeholder="Manual de usuario"
                        />
                        <InputError
                            message={errors[`documentos.${index}.nombre`]}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`documento-archivo-${id}`}>
                            Archivo
                        </Label>
                        <Input
                            id={`documento-archivo-${id}`}
                            name={`documentos[${index}][archivo]`}
                            type="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp"
                        />
                        <InputError
                            message={errors[`documentos.${index}.archivo`]}
                        />
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="sm:mt-6"
                        onClick={() => removeRow(id)}
                        aria-label="Quitar documento"
                        data-test="equipo-documento-row-remove"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addRow}
                data-test="equipo-documento-row-add"
            >
                <Plus className="h-4 w-4" /> Agregar documento
            </Button>
        </div>
    );
}
