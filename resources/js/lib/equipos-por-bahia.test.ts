import { describe, expect, it } from 'vitest';
import { equiposPorBahia } from './equipos-por-bahia';

const programaciones = [
    { bahia_id: 1, equipo_id: 10, fecha: '2026-06-05' },
    // El mismo equipo con otra programación (ej. calibración) cuenta una sola vez.
    { bahia_id: 1, equipo_id: 10, fecha: '2026-06-20' },
    { bahia_id: 1, equipo_id: 11, fecha: '2026-07-15' },
    { bahia_id: 2, equipo_id: 20, fecha: '2026-06-30' },
];

describe('equiposPorBahia', () => {
    it('counts distinct equipos per bahía whose fecha falls in the range, both ends included', () => {
        const conteo = equiposPorBahia(
            programaciones,
            '2026-06-05',
            '2026-06-30',
        );

        expect(conteo.get(1)).toBe(1);
        expect(conteo.get(2)).toBe(1);
    });

    it('leaves out bahías with nothing in the range', () => {
        expect(
            equiposPorBahia(programaciones, '2026-07-01', '2026-07-31').has(2),
        ).toBe(false);
    });

    it('counts every pending one while a fecha is missing', () => {
        const conteo = equiposPorBahia(programaciones, '', '2026-06-30');

        expect(conteo.get(1)).toBe(2);
        expect(conteo.get(2)).toBe(1);
    });
});
