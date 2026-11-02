import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    /** Nombre con el que viaja en el formulario, ej. `equipos_listos[5][fecha_programada]`. */
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
};

/**
 * Día para el que se programa el servicio al agendarlo: con él, el técnico o el tercero ve
 * cuánto plazo tiene y si quedó vencido.
 */
export default function FechaProgramadaField({
    id,
    name,
    value,
    onChange,
    error,
}: Props) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                Fecha programada <span className="text-destructive">*</span>
            </Label>
            <Input
                id={id}
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required
                className="w-full"
                data-test="agendar-fecha-programada"
            />
            <input type="hidden" name={name} value={value} />
            <InputError message={error} />
        </div>
    );
}
