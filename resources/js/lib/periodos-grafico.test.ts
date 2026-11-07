import { describe, expect, it } from 'vitest';
import {
    agrupacionDisponible,
    COLOR_CUMPLE,
    COLOR_NO_CUMPLE,
    cortesDeColor,
    agruparPromedios,
    agrupacionSugerida,
    etiquetaPeriodo,
    inicioDePeriodo,
    menorEsMejor,
    objetivoDelPeriodo,
} from './periodos-grafico';

describe('inicioDePeriodo', () => {
    it('finds the first day of each kind of period', () => {
        // Jueves 5 de marzo de 2026.
        expect(inicioDePeriodo('2026-03-05', 'dia')).toBe('2026-03-05');
        expect(inicioDePeriodo('2026-03-05', 'semana')).toBe('2026-03-02');
        expect(inicioDePeriodo('2026-03-05', 'mes')).toBe('2026-03-01');
        expect(inicioDePeriodo('2026-05-20', 'trimestre')).toBe('2026-04-01');
        expect(inicioDePeriodo('2026-08-20', 'semestre')).toBe('2026-07-01');
        expect(inicioDePeriodo('2026-08-20', 'anio')).toBe('2026-01-01');
    });
});

describe('etiquetaPeriodo', () => {
    it('names each period briefly', () => {
        expect(etiquetaPeriodo('2026-03-02', 'semana')).toBe('sem 2 mar');
        expect(etiquetaPeriodo('2026-04-01', 'trimestre')).toBe('T2 2026');
        expect(etiquetaPeriodo('2026-07-01', 'semestre')).toBe('S2 2026');
    });
});

describe('agruparPromedios', () => {
    it('averages the values of each period and keeps empty periods', () => {
        const puntos = agruparPromedios(
            [
                { fecha: '2026-01-10', valor: 2 },
                { fecha: '2026-01-20', valor: 5 },
                { fecha: '2026-01-25', valor: null },
                { fecha: '2026-03-01', valor: 4 },
            ],
            '2026-01-01',
            '2026-03-31',
            'mes',
        );

        expect(puntos.map((punto) => punto.inicio)).toEqual([
            '2026-01-01',
            '2026-02-01',
            '2026-03-01',
        ]);
        expect(puntos[0]).toMatchObject({ valor: 3.5, cantidad: 3 });
        expect(puntos[1]).toMatchObject({ valor: null, cantidad: 0 });
        expect(puntos[2]).toMatchObject({ valor: 4, cantidad: 1 });
    });
});

describe('agrupacionSugerida', () => {
    it('picks a grouping that fits the length of the period', () => {
        expect(agrupacionSugerida('2026-03-01', '2026-03-07')).toBe('dia');
        expect(agrupacionSugerida('2026-01-01', '2026-03-31')).toBe('semana');
        expect(agrupacionSugerida('2025-04-01', '2026-03-31')).toBe('mes');
        expect(agrupacionSugerida('2023-01-01', '2026-03-31')).toBe(
            'trimestre',
        );
    });
});

describe('objetivoDelPeriodo', () => {
    const objetivos = [
        { anio: 2025, valores: { TIEMPO_ATENCION_DIAS: 6 } },
        {
            anio: 2026,
            valores: { TIEMPO_ATENCION_DIAS: 5, CANTIDAD_MANTENIMIENTOS: 480 },
        },
    ];

    it('uses the goal of the year where the period starts', () => {
        expect(
            objetivoDelPeriodo(
                objetivos,
                '2025-12-01',
                'TIEMPO_ATENCION_DIAS',
                'mes',
            ),
        ).toBe(6);
        expect(
            objetivoDelPeriodo(
                objetivos,
                '2026-01-01',
                'TIEMPO_ATENCION_DIAS',
                'mes',
            ),
        ).toBe(5);
    });

    it('splits yearly quantities by the periods of a year', () => {
        expect(
            objetivoDelPeriodo(
                objetivos,
                '2026-01-01',
                'CANTIDAD_MANTENIMIENTOS',
                'trimestre',
            ),
        ).toBe(120);
        expect(
            objetivoDelPeriodo(
                objetivos,
                '2025-01-01',
                'CANTIDAD_MANTENIMIENTOS',
                'mes',
            ),
        ).toBeNull();
    });
});

describe('menorEsMejor', () => {
    it('is true for times and false for quantities and compliance', () => {
        expect(menorEsMejor('TIEMPO_ATENCION_DIAS')).toBe(true);
        expect(menorEsMejor('CUMPLIMIENTO_PORCENTAJE')).toBe(false);
    });
});

describe('agrupacionDisponible', () => {
    it('blocks groupings longer than the period', () => {
        // Última semana: 8 días.
        const semana = ['2026-03-01', '2026-03-08'] as const;
        expect(agrupacionDisponible('dia', ...semana)).toBe(true);
        expect(agrupacionDisponible('semana', ...semana)).toBe(true);
        expect(agrupacionDisponible('mes', ...semana)).toBe(false);
        expect(agrupacionDisponible('anio', ...semana)).toBe(false);

        // Último mes y último año: se habilitan meses y años.
        expect(agrupacionDisponible('mes', '2026-02-06', '2026-03-06')).toBe(
            true,
        );
        expect(
            agrupacionDisponible('trimestre', '2026-02-06', '2026-03-06'),
        ).toBe(false);
        expect(agrupacionDisponible('anio', '2025-03-06', '2026-03-06')).toBe(
            true,
        );
    });

    it('always allows the suggested grouping', () => {
        const periodos = [
            ['2026-03-01', '2026-03-01'],
            ['2026-03-01', '2026-03-08'],
            ['2026-02-06', '2026-03-06'],
            ['2025-12-06', '2026-03-06'],
            ['2025-03-06', '2026-03-06'],
            ['2023-03-06', '2026-03-06'],
        ] as const;

        periodos.forEach(([desde, hasta]) => {
            expect(
                agrupacionDisponible(
                    agrupacionSugerida(desde, hasta),
                    desde,
                    hasta,
                ),
            ).toBe(true);
        });
    });
});

describe('cortesDeColor', () => {
    it('is green while it meets the goal and turns red where the line crosses it', () => {
        // Tiempos: menor es mejor. 4 cumple con 5, 7 no: cruza a un tercio del tramo.
        expect(
            cortesDeColor(
                [
                    { valor: 4, objetivo: 5 },
                    { valor: 7, objetivo: 5 },
                ],
                true,
            ),
        ).toEqual([
            { offset: 0, color: COLOR_CUMPLE },
            { offset: 1 / 3, color: COLOR_CUMPLE },
            { offset: 1 / 3, color: COLOR_NO_CUMPLE },
            { offset: 1, color: COLOR_NO_CUMPLE },
        ]);
    });

    it('measures from the first to the last point with a value and treats no goal as meeting it', () => {
        expect(
            cortesDeColor(
                [
                    { valor: null, objetivo: 5 },
                    { valor: 3, objetivo: null },
                    { valor: 3, objetivo: null },
                ],
                true,
            ),
        ).toEqual([
            { offset: 0, color: COLOR_CUMPLE },
            { offset: 1, color: COLOR_CUMPLE },
        ]);
    });
});
