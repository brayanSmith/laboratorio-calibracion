import { describe, expect, it } from 'vitest';
import { listasDocumentacionModelo } from './documentacion-modelo';

const equipo = (
    tipo: [number, string],
    marca: [number, string],
    modelo: string,
) => ({
    tipo_equipo_id: tipo[0],
    tipo_equipo: { id: tipo[0], nombre: tipo[1] },
    fabricante_id: marca[0],
    fabricante: { id: marca[0], nombre: marca[1] },
    modelo,
});

const equipos = [
    equipo([1, 'Manómetro'], [10, 'WIKA'], 'M-100'),
    equipo([1, 'Manómetro'], [10, 'WIKA'], 'M-100'),
    equipo([1, 'Manómetro'], [10, 'WIKA'], 'M-200'),
    equipo([1, 'Manómetro'], [20, 'Ashcroft'], 'A-1'),
    equipo([2, 'Balanza'], [30, 'Ohaus'], 'B-5'),
];

describe('listasDocumentacionModelo', () => {
    it('only offers the brands of the chosen type and the models of that brand', () => {
        const listas = listasDocumentacionModelo(equipos, {
            tipoEquipoId: '1',
            fabricanteId: '10',
            modelo: '',
        });

        expect(listas.tipos.map((tipo) => tipo.label)).toEqual([
            'Balanza',
            'Manómetro',
        ]);
        expect(listas.marcas.map((marca) => marca.label)).toEqual([
            'Ashcroft',
            'WIKA',
        ]);
        expect(listas.modelos.map((modelo) => modelo.id)).toEqual([
            'M-100',
            'M-200',
        ]);
    });

    it('counts the equipos that match the type, brand and model', () => {
        expect(
            listasDocumentacionModelo(equipos, {
                tipoEquipoId: '1',
                fabricanteId: '10',
                modelo: 'M-100',
            }).coincidentes,
        ).toBe(2);
    });

    it('has no brands or models until a type is chosen', () => {
        const listas = listasDocumentacionModelo(equipos, {
            tipoEquipoId: '',
            fabricanteId: '',
            modelo: '',
        });

        expect(listas.marcas).toEqual([]);
        expect(listas.modelos).toEqual([]);
        expect(listas.coincidentes).toBe(0);
    });
});
