import { describe, expect, it } from 'vitest';
import {
    estadoServicioCalibracion,
    estadoServicioMantenimiento,
} from './estado-servicio';

describe('estadoServicioCalibracion', () => {
    it('keeps pendiente and en proceso, and every ending counts as finalizado', () => {
        expect(estadoServicioCalibracion('PENDIENTE')).toBe('PENDIENTE');
        expect(estadoServicioCalibracion('EN_PROCESO')).toBe('EN_PROCESO');
        expect(estadoServicioCalibracion('FINALIZADO')).toBe('FINALIZADO');
        expect(estadoServicioCalibracion('DEVOLVER_MANTENIMIENTO')).toBe(
            'FINALIZADO',
        );
        expect(estadoServicioCalibracion('FUERA_DE_SERVICIO')).toBe(
            'FINALIZADO',
        );
    });
});

describe('estadoServicioMantenimiento', () => {
    it('treats falta repuestos as still en proceso', () => {
        expect(estadoServicioMantenimiento('FALTA_REPUESTOS')).toBe(
            'EN_PROCESO',
        );
        expect(estadoServicioMantenimiento('FINALIZADO')).toBe('FINALIZADO');
    });
});
