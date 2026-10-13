import {
    columnFilteringFeature,
    constructFilterFn,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    filterFn_includesString,
    globalFilteringFeature,
    rowPaginationFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
} from '@tanstack/react-table';

/**
 * Lowercase the text, drop its accents and read underscores as spaces, so "presion"
 * matches "Presión" and "en proceso" matches "EN_PROCESO".
 */
export const normalizeText = (value: unknown): string =>
    String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .replaceAll('_', ' ');

/**
 * Search filter that ignores case and accents.
 */
export const includesText = constructFilterFn({
    ...filterFn_includesString,
    resolveFilterValue: normalizeText,
    resolveDataValue: normalizeText,
});

/**
 * Exact filter for the columns that offer a list of options (estado, sí/no...): compares
 * the value as text, so booleans and null work too ("true", "false", "null").
 */
export const igualA = constructFilterFn({
    resolveFilterValue: (value: unknown) => String(value),
    resolveDataValue: (value: unknown) => String(value),
    filter: (dataValue: unknown, filterValue: unknown) =>
        dataValue === filterValue,
});

/** One option of a column filter list. */
export type FilterOption = { value: string; label: string };

/** Yes/no options for boolean columns. */
export const OPCIONES_SI_NO: FilterOption[] = [
    { value: 'true', label: 'Sí' },
    { value: 'false', label: 'No' },
];

/**
 * Extra data a column can declare. With `filterOptions` its filter is a dropdown of those
 * options (matched exactly) instead of a free-text box.
 */
export type DataTableColumnMeta = { filterOptions?: FilterOption[] };

/**
 * Gives every column its filter: text without accents or case by default, or an exact match
 * when the column offers a list of options. A column's own `filterFn` is kept.
 */
export function withColumnFilters<
    TColumn extends { filterFn?: unknown; meta?: DataTableColumnMeta },
>(columns: TColumn[]): TColumn[] {
    return columns.map((column) => ({
        ...column,
        filterFn:
            column.filterFn ??
            (column.meta?.filterOptions ? 'igualA' : 'includesText'),
    }));
}

/**
 * Features every data table of the app registers: search, column filters, sorting and
 * pagination.
 */
export const dataTableFeatures = tableFeatures({
    columnMeta: {} as DataTableColumnMeta,
    columnFilteringFeature,
    globalFilteringFeature,
    rowSortingFeature,
    rowPaginationFeature,
    filteredRowModel: createFilteredRowModel(),
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    filterFns: { includesText, igualA },
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
});

export type DataTableFeatures = typeof dataTableFeatures;
