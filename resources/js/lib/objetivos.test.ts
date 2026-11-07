import { describe, expect, it } from 'vitest';
import { separarHoras, unirHoras } from './objetivos';
describe('separarHoras y unirHoras', () => {
    it('turn stored hours into hours and minutes and back', () => {
        expect(separarHoras('1.5')).toEqual({ horas: '1', minutos: '30' });
        expect(separarHoras('0.5')).toEqual({ horas: '0', minutos: '30' });
        expect(unirHoras('1', '30')).toBe('1.5');
        expect(unirHoras('', '30')).toBe('0.5');
    });

    it('round trips minutes that are not an exact fraction', () => {
        expect(separarHoras(unirHoras('0', '20'))).toEqual({
            horas: '0',
            minutos: '20',
        });
    });

    it('is empty when there is no value', () => {
        expect(separarHoras('')).toEqual({ horas: '', minutos: '' });
        expect(unirHoras('', '')).toBe('');
    });
});
