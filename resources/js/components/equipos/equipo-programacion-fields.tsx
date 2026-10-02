import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EquipoProgramacion, IntervaloUnidad } from '@/types';

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

/**
 * Preview only: mirrors EquipoProgramacion::calcularFechaProximoServicio() on the
 * backend, which is what actually computes and stores the value on submit.
 */
function calcularFechaProximoServicio(
    fechaUltimoServicio: string,
    intervalo: string,
    unidad: IntervaloUnidad | undefined,
): string | null {
    const numero = Number(intervalo);

    if (!fechaUltimoServicio || !numero || !unidad) {
        return null;
    }

    const fecha = new Date(`${fechaUltimoServicio}T00:00:00`);

    if (Number.isNaN(fecha.getTime())) {
        return null;
    }

    if (unidad === 'DIAS') {
        fecha.setDate(fecha.getDate() + numero);
    } else if (unidad === 'SEMANAS') {
        fecha.setDate(fecha.getDate() + numero * 7);
    } else {
        fecha.setMonth(fecha.getMonth() + numero);
    }

    return fecha.toISOString().slice(0, 10);
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

export const unidadesIntervalo = [
    { value: 'DIAS', label: 'Días' },
    { value: 'SEMANAS', label: 'Semanas' },
    { value: 'MESES', label: 'Meses' },
];

export const estadosProgramacion = [
    { value: 'PENDIENTE', label: 'Pendiente' },
    { value: 'AGENDADO', label: 'Agendado' },
    { value: 'CANCELADO', label: 'Cancelado' },
];

export default function EquipoProgramacionFields({
    programacion,
    errors,
    fieldName = (key) => key,
    fieldError = (key) => errors[key],
}: Props) {
    const tiposSeleccionados = programacion?.tipo_servicio?.split(',') ?? [];

    const [intervaloServicio, setIntervaloServicio] = useState(
        programacion?.intervalo_servicio ?? '',
    );
    const [intervaloUnidad, setIntervaloUnidad] = useState<
        IntervaloUnidad | undefined
    >(programacion?.intervalo_unidad ?? undefined);
    const [fechaUltimoServicio, setFechaUltimoServicio] = useState(
        toDateInputValue(programacion?.fecha_ultimo_servicio),
    );

    const fechaProximoServicio = calcularFechaProximoServicio(
        fechaUltimoServicio,
        intervaloServicio,
        intervaloUnidad,
    );

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label>Tipo de servicio</Label>
                <div className="flex flex-wrap gap-4">
                    {tiposServicio.map((option) => {
                        const inputId = `${fieldName('tipo_servicio')}-${option.value}`;

                        return (
                            <div
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={inputId}
                                    name={`${fieldName('tipo_servicio')}[]`}
                                    value={option.value}
                                    defaultChecked={tiposSeleccionados.includes(
                                        option.value,
                                    )}
                                />
                                <Label
                                    htmlFor={inputId}
                                    className="text-sm font-normal"
                                >
                                    {option.label}
                                </Label>
                            </div>
                        );
                    })}
                </div>
                <InputError message={fieldError('tipo_servicio')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('intervalo_servicio')}>
                    Intervalo de servicio
                </Label>
                <Input
                    id={fieldName('intervalo_servicio')}
                    name={fieldName('intervalo_servicio')}
                    type="number"
                    step="0.01"
                    value={intervaloServicio}
                    onChange={(event) =>
                        setIntervaloServicio(event.target.value)
                    }
                />
                <InputError message={fieldError('intervalo_servicio')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fieldName('intervalo_unidad')}>
                    Unidad del intervalo
                </Label>
                <Select
                    name={fieldName('intervalo_unidad')}
                    value={intervaloUnidad}
                    onValueChange={(value) =>
                        setIntervaloUnidad(value as IntervaloUnidad)
                    }
                >
                    <SelectTrigger
                        id={fieldName('intervalo_unidad')}
                        className="w-full"
                    >
                        <SelectValue placeholder="Selecciona una unidad" />
                    </SelectTrigger>
                    <SelectContent>
                        {unidadesIntervalo.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={fieldError('intervalo_unidad')} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={fieldName('fecha_ultimo_servicio')}>
                    Fecha del último servicio
                </Label>
                <Input
                    id={fieldName('fecha_ultimo_servicio')}
                    name={fieldName('fecha_ultimo_servicio')}
                    type="date"
                    value={fechaUltimoServicio}
                    onChange={(event) =>
                        setFechaUltimoServicio(event.target.value)
                    }
                />
                <InputError message={fieldError('fecha_ultimo_servicio')} />
                {fechaProximoServicio ? (
                    <p
                        className="text-sm text-muted-foreground"
                        data-test="fecha-proximo-servicio-preview"
                    >
                        Próximo servicio: {fechaProximoServicio}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
