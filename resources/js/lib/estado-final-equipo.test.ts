import { describe, expect, it } from 'vitest';
import { estadoFinalEquipoCalibracion } from './estado-final-equipo';

describe('estadoFinalEquipoCalibracion', () => {
    it('derives how the equipo ended up from how the calibración finished', () => {
        expect(
            estadoFinalEquipoCalibracion({ estado_calibracion: 'FINALIZADO' }),
        ).toBe('OPERATIVO');
        expect(
            estadoFinalEquipoCalibracion({
                estado_calibracion: 'DEVOLVER_MANTENIMIENTO',
            }),
        ).toBe('DEVUELTO_MANTENIMIENTO');
        expect(
            estadoFinalEquipoCalibracion({
                estado_calibracion: 'FUERA_DE_SERVICIO',
            }),
        ).toBe('FUERA_DE_SERVICIO');
    });

    it('is null while the calibración has not finished', () => {
        expect(
            estadoFinalEquipoCalibracion({ estado_calibracion: 'PENDIENTE' }),
        ).toBeNull();
        expect(
            estadoFinalEquipoCalibracion({ estado_calibracion: 'EN_PROCESO' }),
        ).toBeNull();
    });
});
