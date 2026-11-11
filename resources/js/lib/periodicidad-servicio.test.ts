import { describe, expect, it } from 'vitest';
import { etiquetaPeriodicidad } from './periodicidad-servicio';

describe('etiquetaPeriodicidad', () => {
    it('nombra las periodicidades comunes con su intervalo', () => {
        expect(etiquetaPeriodicidad('6.00', 'MESES')).toBe(
            'Semestral (cada 6 meses)',
        );
        expect(etiquetaPeriodicidad(1, 'MESES')).toBe('Mensual (cada mes)');
        expect(etiquetaPeriodicidad(15, 'DIAS')).toBe(
            'Quincenal (cada 15 días)',
        );
        expect(etiquetaPeriodicidad(2, 'SEMANAS')).toBe(
            'Quincenal (cada 2 semanas)',
        );
        expect(etiquetaPeriodicidad(12, 'MESES')).toBe('Anual (cada 12 meses)');
    });

    it('sin un nombre común, solo dice cada cuánto', () => {
        expect(etiquetaPeriodicidad(45, 'DIAS')).toBe('Cada 45 días');
        expect(etiquetaPeriodicidad(5, 'MESES')).toBe('Cada 5 meses');
    });

    it('sin intervalo o sin unidad no hay periodicidad', () => {
        expect(etiquetaPeriodicidad(null, 'MESES')).toBeNull();
        expect(etiquetaPeriodicidad('6', null)).toBeNull();
        expect(etiquetaPeriodicidad('0', 'DIAS')).toBeNull();
    });
});
