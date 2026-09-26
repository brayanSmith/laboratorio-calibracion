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
 * Lowercase the text and drop its accents, so "presion" matches "Presión".
 */
export const normalizeText = (value: unknown): string =>
    String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '');

/**
 * Search filter that ignores case and accents.
 */
export const includesText = constructFilterFn({
    ...filterFn_includesString,
    resolveFilterValue: normalizeText,
    resolveDataValue: normalizeText,
});

/**
 * Features every data table of the app registers: search, sorting and pagination.
 */
export const dataTableFeatures = tableFeatures({
    columnFilteringFeature,
    globalFilteringFeature,
    rowSortingFeature,
    rowPaginationFeature,
    filteredRowModel: createFilteredRowModel(),
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    filterFns: { includesText },
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
});

export type DataTableFeatures = typeof dataTableFeatures;
