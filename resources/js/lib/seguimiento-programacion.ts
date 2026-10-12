import type { FaseSeguimiento } from '@/types';

export const fasesSeguimiento: { value: FaseSeguimiento; label: string }[] = [
    { value: 'BAHIA', label: 'Bahía' },
    { value: 'INGRESO', label: 'Ingreso' },
    { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
    { value: 'CALIBRACION', label: 'Calibración' },
    { value: 'DESPACHO', label: 'Despacho' },
];

export function faseLabel(fase: FaseSeguimiento): string {
    return (
        fasesSeguimiento.find((option) => option.value === fase)?.label ?? fase
    );
}

/** "FALTA_REPUESTOS" -> "Falta repuestos". */
export function seguimientoLabel(seguimiento: string | null): string {
    if (!seguimiento) {
        return 'Sin estado';
    }

    const texto = seguimiento.replaceAll('_', ' ').toLowerCase();

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

export function formatearFechaSeguimiento(iso: string): string {
    return new Date(iso).toLocaleString('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
