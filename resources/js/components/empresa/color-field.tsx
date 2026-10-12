import { Check } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { isHexColor } from '@/lib/empresa-colors';
import { cn } from '@/lib/utils';

export type ColorPreset = {
    nombre: string;
    valor: string;
};

type Props = {
    id: string;
    name: string;
    label: string;
    description: string;
    /** Colores sugeridos que se eligen con un clic. */
    presets: ColorPreset[];
    defaultValue?: string | null;
    error?: string;
};

/**
 * Selector de color que envía su valor hexadecimal con el formulario: una paleta de
 * colores sugeridos y, además, un selector libre para cualquier otro. Vacío significa
 * "usar el color predeterminado" (se guarda como nulo).
 */
export default function ColorField({
    id,
    name,
    label,
    description,
    presets,
    defaultValue,
    error,
}: Props) {
    const [value, setValue] = useState(
        isHexColor(defaultValue) ? defaultValue.toLowerCase() : '',
    );

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div
                className="flex flex-wrap gap-2"
                role="group"
                aria-label={`Colores sugeridos para ${label.toLowerCase()}`}
            >
                {presets.map((preset) => {
                    const seleccionado = value === preset.valor.toLowerCase();

                    return (
                        <button
                            key={preset.valor}
                            type="button"
                            title={preset.nombre}
                            aria-label={preset.nombre}
                            aria-pressed={seleccionado}
                            onClick={() => setValue(preset.valor.toLowerCase())}
                            className={cn(
                                'flex h-8 w-8 items-center justify-center rounded-full border-2 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                seleccionado
                                    ? 'border-foreground'
                                    : 'border-transparent ring-1 ring-border',
                            )}
                            style={{ backgroundColor: preset.valor }}
                            data-test={`${name}-preset`}
                        >
                            {seleccionado ? (
                                <Check
                                    className="h-4 w-4"
                                    style={{
                                        color:
                                            preset.valor === '#ffffff'
                                                ? '#111111'
                                                : '#ffffff',
                                    }}
                                />
                            ) : null}
                        </button>
                    );
                })}
            </div>

            <div className="flex items-center gap-3">
                <input
                    id={id}
                    type="color"
                    value={value || '#000000'}
                    onChange={(event) => setValue(event.target.value)}
                    className="h-9 w-14 cursor-pointer rounded-md border bg-transparent p-1"
                    aria-label={`Otro color para ${label.toLowerCase()}`}
                    data-test={`${name}-input`}
                />
                <span className="font-mono text-sm text-muted-foreground">
                    {value || 'Predeterminado'}
                </span>
                {value ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setValue('')}
                    >
                        Restablecer
                    </Button>
                ) : null}
            </div>
            <input type="hidden" name={name} value={value} />
            <p className="text-xs text-muted-foreground">{description}</p>
            <InputError message={error} />
        </div>
    );
}
