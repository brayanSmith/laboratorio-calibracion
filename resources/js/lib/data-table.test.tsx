import { createColumnHelper, useTable } from '@tanstack/react-table';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { dataTableFeatures, normalizeText } from '@/lib/data-table';
import type { DataTableFeatures } from '@/lib/data-table';

type Row = { nombre: string; descripcion: string | null; total: number };

const helper = createColumnHelper<DataTableFeatures, Row>();
const columns = helper.columns([
    helper.accessor('nombre', { header: 'Nombre' }),
    helper.accessor('descripcion', { header: 'Descripción' }),
    helper.accessor('total', { header: 'Total' }),
]);

const data: Row[] = [
    { nombre: 'Presión', descripcion: null, total: 3 },
    { nombre: 'Temperatura', descripcion: 'Termómetros', total: 10 },
    { nombre: 'Masa', descripcion: 'Balanzas', total: 2 },
    { nombre: 'Torque', descripcion: 'Torquímetros', total: 1 },
];

function visibleNames(initialState: object): string[] {
    function Probe() {
        const table = useTable({
            features: dataTableFeatures,
            columns,
            data,
            globalFilterFn: 'includesText',
            initialState,
        });

        return (
            <ul>
                {table.getRowModel().rows.map((row) => (
                    <li key={row.id}>{row.original.nombre}</li>
                ))}
            </ul>
        );
    }

    return [
        ...renderToStaticMarkup(<Probe />).matchAll(/<li>(.*?)<\/li>/g),
    ].map((match) => match[1]);
}

describe('normalizeText', () => {
    it('ignora mayúsculas y tildes', () => {
        expect(normalizeText('PRESIÓN')).toBe('presion');
        expect(normalizeText(null)).toBe('');
    });
});

describe('dataTableFeatures', () => {
    it('busca sin distinguir tildes ni mayúsculas', () => {
        expect(visibleNames({ globalFilter: 'presion' })).toEqual(['Presión']);
    });

    it('busca también en columnas cuyo primer valor es nulo', () => {
        expect(visibleNames({ globalFilter: 'termometros' })).toEqual([
            'Temperatura',
        ]);
    });

    it('busca en columnas numéricas', () => {
        expect(visibleNames({ globalFilter: '10' })).toEqual(['Temperatura']);
    });

    it('ordena por la columna indicada', () => {
        expect(
            visibleNames({ sorting: [{ id: 'total', desc: true }] }),
        ).toEqual(['Temperatura', 'Presión', 'Masa', 'Torque']);
    });

    it('pagina los resultados', () => {
        expect(
            visibleNames({ pagination: { pageIndex: 1, pageSize: 3 } }),
        ).toEqual(['Torque']);
    });
});
