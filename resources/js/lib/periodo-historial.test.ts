import { describe, expect, it } from 'vitest';
import { fechaEnRango, rangoDelPeriodo } from './periodo-historial';

const hoy = new Date(2026, 9, 20);

describe('rangoDelPeriodo', () => {
    it('counts each period back from today, both ends included', () => {
        expect(rangoDelPeriodo('ULTIMOS_7_DIAS', hoy)).toEqual({
            desde: '2026-10-14',
            hasta: '2026-10-20',
        });
        expect(rangoDelPeriodo('ULTIMO_MES', hoy)).toEqual({
            desde: '2026-09-20',
            hasta: '2026-10-20',
        });
        expect(rangoDelPeriodo('ULTIMOS_3_MESES', hoy).desde).toBe(
            '2026-07-20',
        );
        expect(rangoDelPeriodo('ULTIMO_ANIO', hoy).desde).toBe('2025-10-20');
    });

    it('has no limits for "Todo"', () => {
        expect(rangoDelPeriodo('TODO', hoy)).toEqual({
            desde: null,
            hasta: null,
        });
    });

    it('uses the chosen dates for "Personalizado", leaving an empty one open', () => {
        expect(
            rangoDelPeriodo('PERSONALIZADO', hoy, {
                desde: '2026-01-01',
                hasta: '',
            }),
        ).toEqual({ desde: '2026-01-01', hasta: null });
    });
});

describe('fechaEnRango', () => {
    const rango = { desde: '2026-10-01', hasta: '2026-10-31' };

    it('includes both ends of the range', () => {
        expect(fechaEnRango('2026-10-01', rango)).toBe(true);
        expect(fechaEnRango('2026-10-31', rango)).toBe(true);
        expect(fechaEnRango('2026-09-30', rango)).toBe(false);
        expect(fechaEnRango('2026-11-01', rango)).toBe(false);
    });

    it('excludes records without a date unless the range has no limits', () => {
        expect(fechaEnRango(null, rango)).toBe(false);
        expect(fechaEnRango(null, { desde: null, hasta: null })).toBe(true);
    });
});
