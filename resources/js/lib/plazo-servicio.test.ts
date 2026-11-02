import { describe, expect, it } from 'vitest';
import { etiquetaPlazo, plazoServicio } from './plazo-servicio';

const HOY = '2026-10-28';

describe('plazoServicio', () => {
    it('counts the days left, today and overdue while the servicio is not done', () => {
        expect(plazoServicio('2026-10-31', null, HOY)).toEqual({
            estado: 'PENDIENTE',
            dias: 3,
        });
        expect(plazoServicio('2026-10-28', null, HOY)).toEqual({
            estado: 'VENCE_HOY',
            dias: 0,
        });
        expect(plazoServicio('2026-10-26', null, HOY)).toEqual({
            estado: 'VENCIDO',
            dias: 2,
        });
    });

    it('compares the real date with the scheduled one once it is done', () => {
        expect(plazoServicio('2026-10-20', '2026-10-20', HOY)).toEqual({
            estado: 'A_TIEMPO',
            dias: 0,
        });
        expect(plazoServicio('2026-10-20', '2026-10-18', HOY)?.estado).toBe(
            'A_TIEMPO',
        );
        expect(plazoServicio('2026-10-20', '2026-10-23', HOY)).toEqual({
            estado: 'TARDE',
            dias: 3,
        });
    });

    it('crosses month boundaries and has no plazo without a scheduled date', () => {
        expect(plazoServicio('2026-11-02', null, HOY)?.dias).toBe(5);
        expect(plazoServicio(null, null, HOY)).toBeNull();
    });
});

describe('etiquetaPlazo', () => {
    it('describes each estado in singular or plural', () => {
        expect(etiquetaPlazo({ estado: 'PENDIENTE', dias: 1 })).toBe(
            'Faltan 1 día',
        );
        expect(etiquetaPlazo({ estado: 'VENCIDO', dias: 4 })).toBe(
            'Vencido hace 4 días',
        );
        expect(etiquetaPlazo({ estado: 'TARDE', dias: 2 })).toBe(
            '2 días tarde',
        );
    });
});
