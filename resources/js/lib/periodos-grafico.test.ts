import { describe, expect, it } from 'vitest';
import {
    agrupacionDisponible,
    COLOR_CUMPLE,
    COLOR_NO_CUMPLE,
    cortesDeColor,
    diferenciaConObjetivo,
    objetivoMensualDelPeriodo,
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
            valores: { TIEMPO_ATENCION_DIAS: 5 },
        },
    ];

    it('uses the goal of the year where the period starts', () => {
        expect(
            objetivoDelPeriodo(objetivos, '2025-12-01', 'TIEMPO_ATENCION_DIAS'),
        ).toBe(6);
        expect(
            objetivoDelPeriodo(objetivos, '2026-01-01', 'TIEMPO_ATENCION_DIAS'),
        ).toBe(5);
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

describe('diferenciaConObjetivo', () => {
    it('meets the goal below it for times and above it for quantities', () => {
        expect(diferenciaConObjetivo(6.5, 5, true)).toEqual({
            diferencia: 1.5,
            cumple: false,
        });
        expect(diferenciaConObjetivo(42, 40, false)).toEqual({
            diferencia: 2,
            cumple: true,
        });
        expect(diferenciaConObjetivo(3, null, true)).toBeNull();
    });
});

describe('objetivoMensualDelPeriodo', () => {
    const objetivos = [
        {
            anio: 2026,
            mes: 3,
            tipo_equipo_id: 1,
            valores: {
                CANTIDAD_MANTENIMIENTOS: 20,
                PROMEDIO_HORAS_MANTENIMIENTO: 2,
            },
        },
        {
            anio: 2026,
            mes: 3,
            tipo_equipo_id: 2,
            valores: {
                CANTIDAD_MANTENIMIENTOS: 10,
                PROMEDIO_HORAS_MANTENIMIENTO: 5,
            },
        },
        {
            anio: 2026,
            mes: 4,
            tipo_equipo_id: 1,
            valores: { CANTIDAD_MANTENIMIENTOS: 30 },
        },
    ];

    it('adds up the quantities of the types and of the months in the period', () => {
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-03-01',
                'mes',
                'CANTIDAD_MANTENIMIENTOS',
            ),
        ).toBe(30);
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-01-01',
                'trimestre',
                'CANTIDAD_MANTENIMIENTOS',
            ),
        ).toBe(30);
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-04-01',
                'trimestre',
                'CANTIDAD_MANTENIMIENTOS',
            ),
        ).toBe(30);
    });

    it('takes the share of a month that a day covers', () => {
        // Marzo tiene 31 días: un día es 30 / 31 del objetivo del mes.
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-03-10',
                'dia',
                'CANTIDAD_MANTENIMIENTOS',
            ),
        ).toBeCloseTo(30 / 31);
    });

    it('weights times by how many services each type had', () => {
        const filas = [
            { fecha: '2026-03-02', tipo_equipo_id: 1 },
            { fecha: '2026-03-03', tipo_equipo_id: 1 },
            { fecha: '2026-03-04', tipo_equipo_id: 1 },
            { fecha: '2026-03-05', tipo_equipo_id: 2 },
        ];

        // (2 h × 3 + 5 h × 1) / 4 servicios.
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                filas,
                '2026-03-01',
                'mes',
                'PROMEDIO_HORAS_MANTENIMIENTO',
            ),
        ).toBe(2.75);
        // Sin servicios: promedio simple de los tipos.
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-03-01',
                'mes',
                'PROMEDIO_HORAS_MANTENIMIENTO',
            ),
        ).toBe(3.5);
    });

    it('is null when no month of the period has a goal', () => {
        expect(
            objetivoMensualDelPeriodo(
                objetivos,
                [],
                '2026-06-01',
                'mes',
                'CANTIDAD_MANTENIMIENTOS',
            ),
        ).toBeNull();
    });
});
