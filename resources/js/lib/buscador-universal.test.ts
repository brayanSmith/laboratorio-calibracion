import { describe, expect, it } from 'vitest';
import { esAtajoBuscador, filtrarModulos } from './buscador-universal';

const groups = [
    { items: [{ title: 'Dashboard', href: '/dashboard' }] },
    {
        title: 'Catálogos',
        items: [
            { title: 'Fabricantes', href: '/fabricantes' },
            { title: 'Tipos de magnitud', href: '/tipos-magnitud' },
        ],
    },
    { title: 'Operación', items: [{ title: 'Calibraciones', href: '/c' }] },
];

describe('filtrarModulos', () => {
    it('returns every module when there is nothing to search', () => {
        expect(filtrarModulos(groups, '  ')).toHaveLength(4);
    });

    it('matches the module or its group, ignoring accents and case', () => {
        expect(
            filtrarModulos(groups, 'CALIBRACION').map((m) => m.title),
        ).toEqual(['Calibraciones']);
        expect(filtrarModulos(groups, 'catalogos').map((m) => m.title)).toEqual(
            ['Fabricantes', 'Tipos de magnitud'],
        );
    });
});

describe('esAtajoBuscador', () => {
    it('opens with Ctrl + K or ⌘ + K, but not with K alone', () => {
        expect(
            esAtajoBuscador({ key: 'k', ctrlKey: true, metaKey: false }),
        ).toBe(true);
        expect(
            esAtajoBuscador({ key: 'K', ctrlKey: false, metaKey: true }),
        ).toBe(true);
        expect(
            esAtajoBuscador({ key: 'k', ctrlKey: false, metaKey: false }),
        ).toBe(false);
    });
});
