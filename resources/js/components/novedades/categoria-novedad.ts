import type { CategoriaNovedad } from '@/types';

export const categoriasNovedad: { value: CategoriaNovedad; label: string }[] = [
    { value: 'INGRESO', label: 'Ingreso' },
    { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
    { value: 'CALIBRACION', label: 'Calibración' },
    { value: 'SALIDA', label: 'Salida' },
];

export function categoriaNovedadLabel(value: CategoriaNovedad): string {
    return (
        categoriasNovedad.find((categoria) => categoria.value === value)
            ?.label ?? value
    );
}
