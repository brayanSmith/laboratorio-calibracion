import { createColumnHelper, useTable } from '@tanstack/react-table';
import type { ColumnDef, RowData } from '@tanstack/react-table';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    Search,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dataTableFeatures } from '@/lib/data-table';
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
};

export default function DataTable<TData extends RowData>({
    data,
    columns,
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'No hay registros.',
    pageSize = 10,
    rowTestId,
}: Props<TData>) {
    const table = useTable({
        features: dataTableFeatures,
        columns,
        data,
        globalFilterFn: 'includesText',
        initialState: { pagination: { pageIndex: 0, pageSize } },
    });

    const rows = table.getRowModel().rows;
    const totalRows = table.getPrePaginatedRowModel().rows.length;
    const { pageIndex } = table.state.pagination;
    const firstRow = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const lastRow = pageIndex * pageSize + rows.length;
    const globalFilter = (table.state.globalFilter as string | undefined) ?? '';

    return (
        <div className="space-y-4">
            <div className="relative max-w-sm">
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

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <th
                                            key={header.id}
                                            className="px-4 py-3 font-medium"
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
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
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
                                    {globalFilter !== ''
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
