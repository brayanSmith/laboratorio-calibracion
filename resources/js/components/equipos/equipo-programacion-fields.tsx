import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EquipoProgramacion } from '@/types';

type Props = {
    programacion?: EquipoProgramacion | null;
    errors: Partial<Record<string, string>>;
    /** Turns a plain field key into the actual input `name`, e.g. for a repeated row. */
    fieldName?: (key: string) => string;
    /** Turns a plain field key into the dotted key `errors` uses, e.g. for a repeated row. */
    fieldError?: (key: string) => string | undefined;
};

/**
 * Turn an ISO date/datetime string into the plain YYYY-MM-DD value a date input expects.
 */
function toDateInputValue(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

export const tiposServicio = [
    { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
    { value: 'CALIBRACION', label: 'Calibración' },
];

export const estadosVencimiento = [
    { value: 'AL_DIA', label: 'Al día' },
    { value: 'PROXIMO_A_VENCER', label: 'Próximo a vencer' },
    { value: 'VENCIDO', label: 'Vencido' },
];

export default function EquipoProgramacionFields({
    programacion,
    errors,
    fieldName = (key) => key,
    fieldError = (key) => errors[key],
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={fieldName('tipo_servicio')}>
                    Tipo de servicio
                </Label>
                <Select
                    name={fieldName('tipo_servicio')}
                    defaultValue={programacion?.tipo_servicio}
                >
                    <SelectTrigger
                        id={fieldName('tipo_servicio')}
                        className="w-full"
                    >
                        <SelectValue placeholder="Selecciona un tipo de servicio" />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposServicio.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={fieldError('tipo_servicio')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('estado_vencimiento')}>
                    Estado de vencimiento
                </Label>
                <Select
                    name={fieldName('estado_vencimiento')}
                    defaultValue={programacion?.estado_vencimiento}
                >
                    <SelectTrigger
                        id={fieldName('estado_vencimiento')}
                        className="w-full"
                    >
                        <SelectValue placeholder="Selecciona un estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {estadosVencimiento.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={fieldError('estado_vencimiento')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('intervalo_servicio')}>
                    Intervalo de servicio (días)
                </Label>
                <Input
                    id={fieldName('intervalo_servicio')}
                    name={fieldName('intervalo_servicio')}
                    type="number"
                    step="0.01"
                    defaultValue={programacion?.intervalo_servicio ?? ''}
                />
                <InputError message={fieldError('intervalo_servicio')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('dias_plazo_vencimiento')}>
                    Días de plazo de vencimiento
                </Label>
                <Input
                    id={fieldName('dias_plazo_vencimiento')}
                    name={fieldName('dias_plazo_vencimiento')}
                    type="number"
                    step="0.01"
                    defaultValue={programacion?.dias_plazo_vencimiento}
                    required
                />
                <InputError message={fieldError('dias_plazo_vencimiento')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('fecha_apertura_historial_servicio')}>
                    Apertura del historial
                </Label>
                <Input
                    id={fieldName('fecha_apertura_historial_servicio')}
                    name={fieldName('fecha_apertura_historial_servicio')}
                    type="date"
                    defaultValue={toDateInputValue(
                        programacion?.fecha_apertura_historial_servicio,
                    )}
                />
                <InputError
                    message={fieldError('fecha_apertura_historial_servicio')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('fecha_ultimo_servicio')}>
                    Fecha del último servicio
                </Label>
                <Input
                    id={fieldName('fecha_ultimo_servicio')}
                    name={fieldName('fecha_ultimo_servicio')}
                    type="date"
                    defaultValue={toDateInputValue(
                        programacion?.fecha_ultimo_servicio,
                    )}
                />
                <InputError message={fieldError('fecha_ultimo_servicio')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('fecha_proximo_servicio')}>
                    Fecha del próximo servicio
                </Label>
                <Input
                    id={fieldName('fecha_proximo_servicio')}
                    name={fieldName('fecha_proximo_servicio')}
                    type="date"
                    defaultValue={toDateInputValue(
                        programacion?.fecha_proximo_servicio,
                    )}
                />
                <InputError message={fieldError('fecha_proximo_servicio')} />
            </div>
        </div>
    );
}
