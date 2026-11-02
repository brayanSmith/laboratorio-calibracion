import { describe, expect, it } from 'vitest';
import {
    filtrosConValor,
    formatearDias,
    formatearHoras,
    formatearPorcentaje,
    rangoDePeriodo,
    tonoMapaCalor,
} from './indicadores';

describe('formatearHoras', () => {
    it('shows hours and minutes, or only minutes under an hour', () => {
        expect(formatearHoras(16.58)).toBe('16 h 35 min');
        expect(formatearHoras(2)).toBe('2 h');
        expect(formatearHoras(0.5)).toBe('30 min');
        expect(formatearHoras(null)).toBe('—');
    });
});

describe('formatearDias y formatearPorcentaje', () => {
    it('use singular, the decimal comma and a dash when there is no value', () => {
        expect(formatearDias(1)).toBe('1 día');
        expect(formatearDias(3.5)).toBe('3,5 días');
        expect(formatearDias(null)).toBe('—');
        expect(formatearPorcentaje(25)).toBe('25 %');
        expect(formatearPorcentaje(null)).toBe('—');
    });
});

describe('filtrosConValor', () => {
    it('drops empty filters', () => {
        expect(
            filtrosConValor({
                desde: '2026-11-01',
                laboratorio: '',
                area_id: undefined,
            }),
        ).toEqual({ desde: '2026-11-01' });
    });
});

describe('rangoDePeriodo', () => {
    const hoy = new Date(2026, 2, 31);

    it('counts back from today, and yesterday is a single day', () => {
        expect(rangoDePeriodo('hoy', hoy)).toEqual({
            desde: '2026-03-31',
            hasta: '2026-03-31',
        });
        expect(rangoDePeriodo('ayer', hoy)).toEqual({
            desde: '2026-03-30',
            hasta: '2026-03-30',
        });
        expect(rangoDePeriodo('ultima_semana', hoy)).toEqual({
            desde: '2026-03-24',
            hasta: '2026-03-31',
        });
        expect(rangoDePeriodo('ultimo_trimestre', hoy)).toEqual({
            desde: '2025-12-31',
            hasta: '2026-03-31',
        });
        expect(rangoDePeriodo('ultimo_anio', hoy)).toEqual({
            desde: '2025-03-31',
            hasta: '2026-03-31',
        });
    });

    it('uses the last day of the month when the same day does not exist', () => {
        expect(rangoDePeriodo('ultimo_mes', hoy)).toEqual({
            desde: '2026-02-28',
            hasta: '2026-03-31',
        });
    });

    it('has no range of its own for personalizado', () => {
        expect(rangoDePeriodo('personalizado', hoy)).toBeNull();
    });
});

describe('tonoMapaCalor', () => {
    it('goes from green at the minimum through yellow to red at the maximum', () => {
        expect(tonoMapaCalor(0, 0, 10)).toBe(120);
        expect(tonoMapaCalor(5, 0, 10)).toBe(50);
        expect(tonoMapaCalor(10, 0, 10)).toBe(0);
    });

    it('is green when every value is the same', () => {
        expect(tonoMapaCalor(3, 3, 3)).toBe(120);
    });
});
