import type { IntervaloUnidad } from '@/types';

/** Nombres de las periodicidades más comunes, por unidad y cantidad. */
const NOMBRES: Record<IntervaloUnidad, Record<number, string>> = {
    DIAS: { 1: 'Diario', 7: 'Semanal', 15: 'Quincenal', 30: 'Mensual' },
    SEMANAS: { 1: 'Semanal', 2: 'Quincenal', 4: 'Mensual' },
    MESES: {
        1: 'Mensual',
        2: 'Bimestral',
        3: 'Trimestral',
        4: 'Cuatrimestral',
        6: 'Semestral',
        12: 'Anual',
        24: 'Bienal',
    },
};

const UNIDADES: Record<IntervaloUnidad, [string, string]> = {
    DIAS: ['día', 'días'],
    SEMANAS: ['semana', 'semanas'],
    MESES: ['mes', 'meses'],
};

/**
 * Cada cuánto se programa un servicio: el nombre de la periodicidad (ej. "Semestral") y
 * el intervalo en palabras (ej. "cada 6 meses"). Nulo si la programación no tiene
 * intervalo (ej. un correctivo).
 */
export function periodicidadServicio(
    intervalo: string | number | null | undefined,
    unidad: IntervaloUnidad | null | undefined,
): { nombre: string | null; cada: string } | null {
    const cantidad = Number(intervalo);

    if (!unidad || !Number.isFinite(cantidad) || cantidad <= 0) {
        return null;
    }

    const entero = Number.isInteger(cantidad);
    const [singular, plural] = UNIDADES[unidad];

    return {
        nombre: entero ? (NOMBRES[unidad][cantidad] ?? null) : null,
        cada:
            cantidad === 1
                ? `cada ${singular}`
                : `cada ${cantidad.toLocaleString('es-CO')} ${plural}`,
    };
}

/** "Semestral (cada 6 meses)", o solo "Cada 45 días" si no tiene un nombre común. */
export function etiquetaPeriodicidad(
    intervalo: string | number | null | undefined,
    unidad: IntervaloUnidad | null | undefined,
): string | null {
    const periodicidad = periodicidadServicio(intervalo, unidad);

    if (!periodicidad) {
        return null;
    }

    return periodicidad.nombre
        ? `${periodicidad.nombre} (${periodicidad.cada})`
        : periodicidad.cada.charAt(0).toUpperCase() + periodicidad.cada.slice(1);
}
