import { createColumnHelper, useTable } from '@tanstack/react-table';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import {
    dataTableFeatures,
    normalizeText,
    withColumnFilters,
} from '@/lib/data-table';
import type { DataTableFeatures } from '@/lib/data-table';

type Row = {
    nombre: string;
    descripcion: string | null;
    total: number;
    estado: string;
    activo: boolean | null;
};

const helper = createColumnHelper<DataTableFeatures, Row>();
const columns = helper.columns([
    helper.accessor('nombre', { header: 'Nombre' }),
    helper.accessor('descripcion', { header: 'Descripción' }),
    helper.accessor('total', { header: 'Total' }),
    helper.accessor('estado', { header: 'Estado' }),
    helper.accessor('activo', { header: 'Activo', filterFn: 'igualA' }),
]);

const data: Row[] = [
    {
        nombre: 'Presión',
        descripcion: null,
        total: 3,
        estado: 'EN_PROCESO',
        activo: true,
    },
    {
        nombre: 'Temperatura',
        descripcion: 'Termómetros',
        total: 10,
        estado: 'FINALIZADO',
        activo: false,
    },
    {
        nombre: 'Masa',
        descripcion: 'Balanzas',
        total: 2,
        estado: 'EN_PROCESO',
        activo: null,
    },
    {
        nombre: 'Torque',
        descripcion: 'Torquímetros',
        total: 1,
        estado: 'PENDIENTE',
        activo: true,
    },
];

function visibleNames(initialState: object): string[] {
    function Probe() {
        const table = useTable({
            features: dataTableFeatures,
            columns: withColumnFilters(columns) as unknown as typeof columns,
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
    it('lee los guiones bajos como espacios', () => {
        expect(normalizeText('EN_PROCESO')).toBe('en proceso');
    });

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

    it('filtra por una columna sin distinguir tildes ni mayúsculas', () => {
        expect(
            visibleNames({
                columnFilters: [{ id: 'descripcion', value: 'TERMOMETROS' }],
            }),
        ).toEqual(['Temperatura']);
    });

    it('filtra un estado escribiéndolo como se lee en pantalla', () => {
        expect(
            visibleNames({
                columnFilters: [{ id: 'estado', value: 'en proceso' }],
            }),
        ).toEqual(['Presión', 'Masa']);
    });

    it('combina el filtro de varias columnas', () => {
        expect(
            visibleNames({
                columnFilters: [
                    { id: 'estado', value: 'en proceso' },
                    { id: 'total', value: '3' },
                ],
            }),
        ).toEqual(['Presión']);
    });

    it('filtra por coincidencia exacta cuando la columna ofrece opciones', () => {
        expect(
            visibleNames({ columnFilters: [{ id: 'activo', value: 'true' }] }),
        ).toEqual(['Presión', 'Torque']);
        expect(
            visibleNames({ columnFilters: [{ id: 'activo', value: 'false' }] }),
        ).toEqual(['Temperatura']);
    });

    it('permite filtrar por valores vacíos con la opción "null"', () => {
        expect(
            visibleNames({ columnFilters: [{ id: 'activo', value: 'null' }] }),
        ).toEqual(['Masa']);
    });
});
