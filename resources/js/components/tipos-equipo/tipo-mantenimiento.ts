import type { TipoMantenimiento } from '@/types';

type TipoMantenimientoOption = {
    value: TipoMantenimiento;
    shortLabel: string;
    label: string;
};

export const tiposMantenimiento: TipoMantenimientoOption[] = [
    {
        value: 'A',
        shortLabel: 'A: Instrumentos de medición',
        label: 'A: Instrumentos de medición (requiere mantenimiento y calibración)',
    },
    {
        value: 'B',
        shortLabel: 'B: Herramientas especiales',
        label: 'B: Herramientas especiales (sólo requiere mantenimiento)',
    },
];

export function tipoMantenimientoShortLabel(value: TipoMantenimiento): string {
    return (
        tiposMantenimiento.find((option) => option.value === value)
            ?.shortLabel ?? value
    );
}
