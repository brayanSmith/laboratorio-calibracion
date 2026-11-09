import { describe, expect, it } from 'vitest';
import {
    esPersonalizado,
    replicarHaciaAdelante,
    totalDelAnio,
} from './objetivos-usuario';

const vacios = () => Array<string>(12).fill('');

describe('replicarHaciaAdelante', () => {
    it('escribir en el primer mes llena todo el año', () => {
        expect(replicarHaciaAdelante(vacios(), 0, '10')).toEqual(
            Array(12).fill('10'),
        );
    });

    it('cambiar un mes ajusta los siguientes, no los anteriores', () => {
        const resultado = replicarHaciaAdelante(Array(12).fill('10'), 6, '12');

        expect(resultado.slice(0, 6)).toEqual(Array(6).fill('10'));
        expect(resultado.slice(6)).toEqual(Array(6).fill('12'));
    });

    it('se detiene en un mes que ya se ajustó a otro valor', () => {
        const valores = [...Array(6).fill('10'), ...Array(6).fill('12')];
        const resultado = replicarHaciaAdelante(valores, 2, '8');

        expect(resultado.slice(0, 2)).toEqual(['10', '10']);
        expect(resultado.slice(2, 6)).toEqual(Array(4).fill('8'));
        expect(resultado.slice(6)).toEqual(Array(6).fill('12'));
    });

    it('borrar un mes también vacía los que lo seguían', () => {
        const resultado = replicarHaciaAdelante(Array(12).fill('5'), 10, '');

        expect(resultado.slice(10)).toEqual(['', '']);
        expect(resultado[9]).toBe('5');
    });
});

describe('esPersonalizado', () => {
    it('solo cuando los meses no tienen todos el mismo objetivo', () => {
        expect(esPersonalizado(vacios())).toBe(false);
        expect(esPersonalizado(Array(12).fill('10'))).toBe(false);
        expect(esPersonalizado([...Array(11).fill('10'), '12'])).toBe(true);
        expect(esPersonalizado([...Array(11).fill('10'), ''])).toBe(true);
    });
});

describe('totalDelAnio', () => {
    it('suma solo los meses con valor', () => {
        expect(totalDelAnio(['10', '', '2.5', ...Array(9).fill('')])).toBe(
            12.5,
        );
        expect(totalDelAnio(vacios())).toBeNull();
    });
});
