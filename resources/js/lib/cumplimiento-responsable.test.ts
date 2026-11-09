import { describe, expect, it } from 'vitest';
import type { DesempenoTecnicos } from '@/types';
import {
    cumplimientoDelRegistro,
    cumplimientoDelResponsable,
    cumplimientoDeTiempo,
    porcentajeDeCumplimiento,
    resumenDeCumplimiento,
} from './cumplimiento-responsable';

const tecnico: DesempenoTecnicos['responsables'][number] = {
    tipo: 'TECNICO',
    responsable_id: 7,
    responsable: 'Ana',
    mantenimientos: { cantidad: 12, promedio_horas: 2.5 },
    calibraciones: { cantidad: 0, promedio_horas: null },
};

const datos = {
    responsables: [tecnico],
    objetivos: [
        {
            anio: 2026,
            mes: 3,
            tipo_equipo_id: 1,
            valores: { PROMEDIO_HORAS_MANTENIMIENTO: 3 },
        },
    ],
    objetivos_usuarios: [
        { user_id: 7, anio: 2026, mes: 3, tipo: 'MANTENIMIENTO', valor: 15 },
        { user_id: 8, anio: 2026, mes: 3, tipo: 'MANTENIMIENTO', valor: 40 },
    ],
    servicios: [
        {
            fecha: '2026-03-04',
            tipo_servicio: 'MANTENIMIENTO',
            responsable_tipo: 'TECNICO',
            responsable_id: 7,
            tipo_equipo_id: 1,
            horas: 2.5,
        },
    ],
} as unknown as DesempenoTecnicos;

describe('porcentajeDeCumplimiento', () => {
    it('is the share of the goal that was done', () => {
        expect(porcentajeDeCumplimiento(12, 15)).toEqual({
            objetivo: 15,
            porcentaje: 80,
        });
        expect(porcentajeDeCumplimiento(3, null)).toBeNull();
        expect(porcentajeDeCumplimiento(3, 0)).toBeNull();
    });
});

describe('cumplimientoDelResponsable', () => {
    it('compares quantity with their own goal and time with the equipment type goal', () => {
        expect(
            cumplimientoDelResponsable(
                datos,
                tecnico,
                'MANTENIMIENTO',
                '2026-03-01',
                '2026-03-31',
            ),
        ).toEqual({
            cantidad: { objetivo: 15, porcentaje: 80 },
            tiempo: {
                objetivo: 3,
                diferencia: -0.5,
                cumple: true,
                porcentaje: 120,
            },
        });
    });

    it('has no quantity goal for third parties nor time without services', () => {
        const tercero = { ...tecnico, tipo: 'TERCERO' as const };

        expect(
            cumplimientoDelResponsable(
                datos,
                tercero,
                'MANTENIMIENTO',
                '2026-03-01',
                '2026-03-31',
            ).cantidad,
        ).toBeNull();
        expect(
            cumplimientoDelResponsable(
                datos,
                tecnico,
                'CALIBRACION',
                '2026-03-01',
                '2026-03-31',
            ),
        ).toEqual({ cantidad: null, tiempo: null });
    });
});

describe('resumenDeCumplimiento', () => {
    const totales = {
        mantenimientos: { cantidad: 12, promedio_horas: 2.5 },
        calibraciones: { cantidad: 3, promedio_horas: 4 },
    };
    const conCalibraciones = {
        ...datos,
        totales,
        objetivos: [
            ...datos.objetivos,
            {
                anio: 2026,
                mes: 3,
                tipo_equipo_id: 1,
                valores: { PROMEDIO_HORAS_CALIBRACION: 5 },
            },
        ],
        objetivos_usuarios: [
            ...datos.objetivos_usuarios,
            { user_id: 7, anio: 2026, mes: 3, tipo: 'CALIBRACION', valor: 5 },
        ],
        servicios: [
            ...datos.servicios,
            {
                ...datos.servicios[0],
                tipo_servicio: 'CALIBRACION',
                horas: 4,
            },
        ],
    } as unknown as DesempenoTecnicos;

    it('sums what was done against every technician goal for one service', () => {
        expect(
            resumenDeCumplimiento(
                conCalibraciones,
                ['MANTENIMIENTO'],
                '2026-03-01',
                '2026-03-31',
            ),
        ).toEqual({
            realizados: 12,
            // 15 de Ana + 40 de otro técnico.
            cantidad: { objetivo: 55, porcentaje: 21.8 },
            horasTotales: 2.5,
            promedioHoras: 2.5,
            tiempo: {
                objetivo: 3,
                diferencia: -0.5,
                cumple: true,
                porcentaje: 120,
            },
        });
    });

    it('combines both services in the overall summary', () => {
        const general = resumenDeCumplimiento(
            conCalibraciones,
            ['MANTENIMIENTO', 'CALIBRACION'],
            '2026-03-01',
            '2026-03-31',
        );

        expect(general.realizados).toBe(15);
        expect(general.cantidad).toEqual({ objetivo: 60, porcentaje: 25 });
        expect(general.horasTotales).toBe(6.5);
        // Promedio 3,25 h frente a (3 h + 5 h) / 2 servicios con tiempo: -0,75 h, redondeado.
        expect(general.tiempo).toEqual({
            objetivo: 4,
            diferencia: -0.7,
            porcentaje: 123.1,
            cumple: true,
        });
    });
});

describe('cumplimientoDeTiempo', () => {
    it('is the goal divided by the time taken', () => {
        expect(cumplimientoDeTiempo(2, 3)).toEqual({
            objetivo: 3,
            diferencia: -1,
            cumple: true,
            porcentaje: 150,
        });
        expect(cumplimientoDeTiempo(4, 3)?.porcentaje).toBe(75);
        expect(cumplimientoDeTiempo(4, 3)?.cumple).toBe(false);
        expect(cumplimientoDeTiempo(null, 3)).toBeNull();
        expect(cumplimientoDeTiempo(2, null)).toBeNull();
    });
});

describe('cumplimientoDelRegistro', () => {
    it('compares one service with the goal of its equipment type in its month', () => {
        expect(
            cumplimientoDelRegistro(datos, datos.servicios[0])?.porcentaje,
        ).toBe(120);
        expect(
            cumplimientoDelRegistro(datos, {
                ...datos.servicios[0],
                fecha: '2026-05-04',
            }),
        ).toBeNull();
    });
});
