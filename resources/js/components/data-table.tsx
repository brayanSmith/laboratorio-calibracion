import { createColumnHelper, useTable } from '@tanstack/react-table';
import { useMemo } from 'react';
import type { ColumnDef, RowData } from '@tanstack/react-table';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    FilterX,
    Search,
} from 'lucide-react';
import ColumnResizeHandle, {
    ColgroupAnchos,
} from '@/components/column-resize-handle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useResizableTable } from '@/hooks/use-resizable-table';
import { cn } from '@/lib/utils';
import { dataTableFeatures, withColumnFilters } from '@/lib/data-table';
import type { DataTableFeatures } from '@/lib/data-table';

export type DataTableColumn<TData extends RowData> = ColumnDef<
    DataTableFeatures,
    TData,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    any
>;

export function createDataTableColumnHelper<TData extends RowData>() {
    return createColumnHelper<DataTableFeatures, TData>();
}

type Props<TData extends RowData> = {
    data: TData[];
    columns: DataTableColumn<TData>[];
    searchPlaceholder?: string;
    emptyMessage?: string;
    pageSize?: number;
    rowTestId?: string;
    /** Filtros de columna con los que arranca la tabla. */
    initialColumnFilters?: { id: string; value: unknown }[];
};

const selectClassName =
    'h-8 w-full min-w-24 rounded-md border border-input bg-background px-2 text-xs font-normal text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

export default function DataTable<TData extends RowData>({
    data,
    columns,
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'No hay registros.',
    pageSize = 10,
    rowTestId,
    initialColumnFilters,
}: Props<TData>) {
    // Cada columna filtra por texto (sin tildes ni mayúsculas) o, si declara opciones, por
    // coincidencia exacta con la opción elegida.
    const columnsConFiltro = useMemo(
        () => withColumnFilters(columns) as DataTableColumn<TData>[],
        [columns],
    );

    const table = useTable({
        features: dataTableFeatures,
        columns: columnsConFiltro,
        data,
        globalFilterFn: 'includesText',
        initialState: {
            pagination: { pageIndex: 0, pageSize },
            ...(initialColumnFilters
                ? { columnFilters: initialColumnFilters }
                : {}),
        },
    });

    const clavesColumnas = table
        .getLeafHeaders()
        .map((header) => header.column.id);
    const { tablaRef, anchos, estiloTabla, iniciarRedimension, restablecer } =
        useResizableTable(clavesColumnas);

    const rows = table.getRowModel().rows;
    const totalRows = table.getPrePaginatedRowModel().rows.length;
    const { pageIndex } = table.state.pagination;
    const firstRow = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const lastRow = pageIndex * pageSize + rows.length;
    const globalFilter = (table.state.globalFilter as string | undefined) ?? '';
    const columnFilters = table.state.columnFilters;
    const hayFiltros = globalFilter !== '' || columnFilters.length > 0;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative w-full max-w-sm">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={globalFilter}
                        onChange={(event) =>
                            table.setGlobalFilter(event.target.value)
                        }
                        placeholder={searchPlaceholder}
                        aria-label={searchPlaceholder}
                        className="pl-9"
                        data-test="data-table-search"
                    />
                </div>

                {hayFiltros ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            table.setGlobalFilter('');
                            table.resetColumnFilters(true);
                        }}
                        data-test="data-table-clear-filters"
                    >
                        <FilterX className="h-4 w-4" /> Limpiar filtros
                    </Button>
                ) : null}
            </div>

            <div className="overflow-x-auto rounded-lg border">
                <table
                    ref={tablaRef}
                    style={estiloTabla}
                    className={cn(
                        'w-full text-sm',
                        anchos && '[&_td]:break-words [&_th]:break-words',
                    )}
                >
                    <ColgroupAnchos claves={clavesColumnas} anchos={anchos} />
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <th
                                            key={header.id}
                                            className="relative px-4 py-3 font-medium"
                                            aria-sort={
                                                sorted === 'asc'
                                                    ? 'ascending'
                                                    : sorted === 'desc'
                                                      ? 'descending'
                                                      : undefined
                                            }
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                    {sorted === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : sorted === 'desc' ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronsUpDown className="h-4 w-4 opacity-50" />
                                                    )}
                                                </button>
                                            ) : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            )}
                                            {header.isPlaceholder ? null : (
                                                <ColumnResizeHandle
                                                    label={header.column.id}
                                                    onPointerDown={iniciarRedimension(
                                                        header.column.id,
                                                    )}
                                                    onDoubleClick={restablecer}
                                                />
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                        <tr className="border-t bg-background">
                            {table.getLeafHeaders().map((header) => {
                                const column = header.column;
                                const opciones =
                                    column.columnDef.meta?.filterOptions;
                                const valor =
                                    (column.getFilterValue() as
                                        string | undefined) ?? '';
                                const etiqueta = `Filtrar ${String(column.columnDef.header ?? column.id)}`;

                                return (
                                    <th
                                        key={header.id}
                                        className="px-4 py-2 font-normal"
                                    >
                                        {!column.getCanFilter() ? null : opciones ? (
                                            <select
                                                value={valor}
                                                onChange={(event) =>
                                                    column.setFilterValue(
                                                        event.target.value ||
                                                            undefined,
                                                    )
                                                }
                                                className={selectClassName}
                                                aria-label={etiqueta}
                                                data-test="data-table-column-filter"
                                            >
                                                <option value="">Todos</option>
                                                {opciones.map((opcion) => (
                                                    <option
                                                        key={opcion.value}
                                                        value={opcion.value}
                                                    >
                                                        {opcion.label}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <Input
                                                value={valor}
                                                onChange={(event) =>
                                                    column.setFilterValue(
                                                        event.target.value ||
                                                            undefined,
                                                    )
                                                }
                                                placeholder="Filtrar..."
                                                aria-label={etiqueta}
                                                className="h-8 min-w-24 text-xs font-normal"
                                                data-test="data-table-column-filter"
                                            />
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr key={row.id} data-test={rowTestId}>
                                {row.getAllCells().map((cell) => (
                                    <td key={cell.id} className="px-4 py-3">
                                        <table.FlexRender cell={cell} />
                                    </td>
                                ))}
                            </tr>
                        ))}

                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {hayFiltros
                                        ? 'No se encontraron resultados.'
                                        : emptyMessage}
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>

            {totalRows > 0 ? (
                <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
                    <span data-test="data-table-summary">
                        Mostrando {firstRow}–{lastRow} de {totalRows}
                    </span>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            aria-label="Página anterior"
                            data-test="data-table-previous"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span>
                            Página {pageIndex + 1} de{' '}
                            {Math.max(table.getPageCount(), 1)}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            aria-label="Página siguiente"
                            data-test="data-table-next"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
