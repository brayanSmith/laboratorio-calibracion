import { describe, expect, it } from 'vitest';
import { estadoAlcanceMedicion } from './alcance-medicion-equipo';

const alcances = [
    { tipo_equipo_id: 3, modelo: 'Fluke 700', alcance_indicacion: '0 - 10bar' },
];

describe('estadoAlcanceMedicion', () => {
    it('is registrado when tipo de equipo, modelo and alcance match, ignoring case and outer spaces', () => {
        expect(
            estadoAlcanceMedicion(alcances, 3, 'Fluke 700', '0 - 10bar'),
        ).toBe('REGISTRADO');
        expect(
            estadoAlcanceMedicion(alcances, '3', ' fluke 700 ', '0 - 10BAR'),
        ).toBe('REGISTRADO');
    });

    it('is pendiente when any of the three differs', () => {
        expect(
            estadoAlcanceMedicion(alcances, 4, 'Fluke 700', '0 - 10bar'),
        ).toBe('PENDIENTE');
        expect(
            estadoAlcanceMedicion(alcances, 3, 'Fluke 701', '0 - 10bar'),
        ).toBe('PENDIENTE');
        expect(
            estadoAlcanceMedicion(alcances, 3, 'Fluke 700', '0 - 20bar'),
        ).toBe('PENDIENTE');
    });

    it('cannot tell while tipo de equipo, modelo or alcance are missing', () => {
        expect(
            estadoAlcanceMedicion(
                alcances,
                undefined,
                'Fluke 700',
                '0 - 10bar',
            ),
        ).toBe('SIN_DATOS');
        expect(estadoAlcanceMedicion(alcances, 3, '  ', '0 - 10bar')).toBe(
            'SIN_DATOS',
        );
        expect(estadoAlcanceMedicion(alcances, 3, 'Fluke 700', null)).toBe(
            'SIN_DATOS',
        );
    });
});
