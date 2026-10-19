import { describe, expect, it } from 'vite-plus/test';
import {
    calcularErrorEncontrado,
    calcularResultado,
    resultadoDeLaCalibracion,
    valoresGuardados,
} from '@/lib/calibracion-resultado';
import type { CalibracionDetalleMedicion } from '@/types';

function detalle(
    id: number,
    overrides: Partial<CalibracionDetalleMedicion> = {},
): CalibracionDetalleMedicion {
    return {
        id,
        valor_referencia: '10',
        unidad_simbolo: 'bar',
        valor_instrumento: null,
        error_encontrado: null,
        emp: '0.5',
        incertidumbre: '0.1',
        error_porcentaje: null,
        emp_porcentaje: null,
        emp_porcentaje_positivo: null,
        emp_porcentaje_negativo: null,
        resultado_calibracion: null,
        ...overrides,
    };
}

describe('calcularErrorEncontrado / calcularResultado', () => {
    it('es la distancia entre el valor de referencia y el del instrumento', () => {
        expect(calcularErrorEncontrado('10', '9.8')).toBeCloseTo(0.2);
    });

    it('es siempre positivo, sin importar hacia dónde se aleje el instrumento', () => {
        expect(calcularErrorEncontrado('10', '10.3')).toBeCloseTo(0.3);
        expect(calcularErrorEncontrado('10', '9.7')).toBeCloseTo(0.3);
    });

    it('no calcula nada mientras el instrumento esté vacío', () => {
        expect(calcularErrorEncontrado('10', '')).toBeNull();
        expect(calcularResultado(null, '0.5')).toBeNull();
    });

    it('no aprueba una lectura que se pasa del EMP por encima de la referencia', () => {
        expect(
            calcularResultado(calcularErrorEncontrado('100', '120'), '2'),
        ).toBe('NO_APROBADO');
        expect(
            calcularResultado(calcularErrorEncontrado('100', '98'), '2'),
        ).toBe('APROBADO');
    });

    it('aprueba si el error no supera el EMP y no aprueba si lo supera', () => {
        expect(calcularResultado(0.5, '0.5')).toBe('APROBADO');
        expect(calcularResultado(0.6, '0.5')).toBe('NO_APROBADO');
    });
});

describe('resultadoDeLaCalibracion', () => {
    const detalles = [detalle(1), detalle(2)];

    it('es null mientras falte completar alguna fila', () => {
        expect(
            resultadoDeLaCalibracion(detalles, { 1: '10', 2: '' }),
        ).toBeNull();
    });

    it('es aprobado cuando todas las filas están aprobadas', () => {
        expect(resultadoDeLaCalibracion(detalles, { 1: '10', 2: '9.9' })).toBe(
            'APROBADO',
        );
    });

    it('es no aprobado en cuanto una fila lo está, aunque falten otras', () => {
        expect(resultadoDeLaCalibracion(detalles, { 1: '5', 2: '' })).toBe(
            'NO_APROBADO',
        );
    });

    it('sin filas de medición queda aprobado, igual que en el servidor', () => {
        expect(resultadoDeLaCalibracion([], {})).toBe('APROBADO');
    });

    it('reacciona cuando cambia lo escrito', () => {
        expect(resultadoDeLaCalibracion(detalles, { 1: '10', 2: '10' })).toBe(
            'APROBADO',
        );
        expect(resultadoDeLaCalibracion(detalles, { 1: '10', 2: '3' })).toBe(
            'NO_APROBADO',
        );
    });
});

describe('valoresGuardados', () => {
    it('parte de lo ya guardado en cada fila', () => {
        const detalles = [
            detalle(1, { valor_instrumento: '10.00' }),
            detalle(2),
        ];

        expect(valoresGuardados(detalles)).toEqual({ 1: '10.00', 2: '' });
    });
});
