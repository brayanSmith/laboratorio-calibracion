import { createColumnHelper, useTable } from '@tanstack/react-table';
import { useCallback, useMemo, useState } from 'react';
import type { KeyboardEvent, MouseEvent, ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import type { ColumnDef, RowData } from '@tanstack/react-table';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    FilterX,
    Search,
    Trash2,
} from 'lucide-react';
import EliminarPorLoteDialog from '@/components/eliminar-por-lote-dialog';
import ColumnResizeHandle, {
    ColgroupAnchos,
} from '@/components/column-resize-handle';
import MenuAccionesFila from '@/components/menu-acciones-fila';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useResizableTable } from '@/hooks/use-resizable-table';
import { cn } from '@/lib/utils';
import { dataTableFeatures, withColumnFilters } from '@/lib/data-table';
import { esFecha, formatearFecha } from '@/lib/fechas';
import { iconoDeColumna } from '@/lib/iconos-columnas';
import { SELECTOR_INTERACTIVO } from '@/lib/menu-acciones-fila';
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
    /** Al tocar una fila (si la tabla no tiene columna de acciones), ej. abrir su detalle. */
    onFilaClick?: (fila: TData) => void;
    /** Clases extra de una fila, ej. para resaltar las prioritarias. */
    claseFila?: (fila: TData) => string | undefined;
    /**
     * Muestra casillas para elegir filas (o todas las filtradas) y eliminarlas de una vez.
     * Pasarla solo si el usuario tiene el permiso de eliminar por lote del módulo; las filas
     * deben tener `id`.
     */
    eliminarPorLote?: {
        url: string;
        /** Nombre en plural de los registros, ej. "ítems". */
        nombre: string;
    };
};

/** Clave de la columna de casillas de selección (no es una columna de TanStack). */
const COLUMNA_SELECCION = '__seleccion';

function idDeFila(fila: unknown): number {
    return (fila as { id: number }).id;
}

/** Columna con los botones de acción de cada fila (la misma que muestra el menú de la fila). */
const COLUMNA_ACCIONES = 'acciones';

/**
 * A las columnas sin celda propia, les muestra las fechas en DD/MM/AAAA (el valor, para
 * ordenar y filtrar, sigue siendo AAAA-MM-DD). Las que tienen celda propia la formatean ahí.
 */
function conFechaFormateada<TData extends RowData>(
    columna: DataTableColumn<TData>,
): DataTableColumn<TData> {
    if (columna.cell) {
        return columna;
    }

    return {
        ...columna,
        cell: (info: { getValue: () => unknown }) => {
            const valor = info.getValue();

            return esFecha(valor)
                ? formatearFecha(valor)
                : (valor as ReactNode);
        },
    } as DataTableColumn<TData>;
}

/** Ícono opcional antes del título de una columna (ver DataTableColumnMeta). */
function IconoColumna({ icono: Icono }: { icono?: LucideIcon | null }) {
    return Icono ? (
        <Icono className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
    ) : null;
}

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
    onFilaClick,
    claseFila,
    eliminarPorLote,
}: Props<TData>) {
    // Cada columna filtra por texto (sin tildes ni mayúsculas) o, si declara opciones, por
    // coincidencia exacta con la opción elegida.
    const columnsConFiltro = useMemo(
        () =>
            withColumnFilters(
                columns.map(conFechaFormateada),
            ) as DataTableColumn<TData>[],
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

    // La columna de acciones no se dibuja en la tabla: sus botones salen en el menú que se
    // abre al hacer clic en la fila, para no recargar cada fila de botones.
    const esVisible = (columnId: string) => columnId !== COLUMNA_ACCIONES;
    const leafHeaders = table
        .getLeafHeaders()
        .filter((header) => esVisible(header.column.id));
    const conSeleccion = eliminarPorLote !== undefined;
    const clavesColumnas = [
        ...(conSeleccion ? [COLUMNA_SELECCION] : []),
        ...leafHeaders.map((header) => header.column.id),
    ];
    const { tablaRef, anchos, estiloTabla, iniciarRedimension, restablecer } =
        useResizableTable(clavesColumnas);

    const rows = table.getRowModel().rows;

    // Selección para eliminar por lote: por id, para que sobreviva a cambiar de página,
    // filtrar u ordenar. Las filas que ya no están en los datos (ej. recién eliminadas)
    // dejan de contar solas.
    const [seleccion, setSeleccion] = useState<Set<number>>(() => new Set());
    const [confirmarLote, setConfirmarLote] = useState(false);
    const idsVigentes = useMemo(() => new Set(data.map(idDeFila)), [data]);
    const seleccionados = [...seleccion].filter((id) => idsVigentes.has(id));
    const idsFiltrados = table
        .getPrePaginatedRowModel()
        .rows.map((row) => idDeFila(row.original));
    const filtradosElegidos = idsFiltrados.filter((id) => seleccion.has(id));
    const todosFiltradosElegidos =
        idsFiltrados.length > 0 &&
        filtradosElegidos.length === idsFiltrados.length;

    const alternarFila = (id: number, elegir: boolean) =>
        setSeleccion((actual) => {
            const nueva = new Set(actual);

            if (elegir) {
                nueva.add(id);
            } else {
                nueva.delete(id);
            }

            return nueva;
        });

    /** La casilla del encabezado elige (o suelta) todas las filas filtradas, de todas las páginas. */
    const alternarFiltrados = (elegir: boolean) =>
        setSeleccion((actual) => {
            const nueva = new Set(actual);

            idsFiltrados.forEach((id) =>
                elegir ? nueva.add(id) : nueva.delete(id),
            );

            return nueva;
        });

    // Menú de acciones al hacer clic en una fila: muestra su columna de acciones junto al
    // clic, para no tener que desplazarse hasta el final de la tabla.
    const tieneAcciones = table
        .getAllLeafColumns()
        .some((column) => column.id === COLUMNA_ACCIONES);
    const [menu, setMenu] = useState<{
        filaId: string;
        clic: { x: number; y: number };
        visible: boolean;
        conTeclado?: boolean;
    } | null>(null);
    const celdaAccionesMenu = menu
        ? rows
              .find((row) => row.id === menu.filaId)
              ?.getAllCells()
              .find((cell) => cell.column.id === COLUMNA_ACCIONES)
        : undefined;

    const abrirMenu = (
        evento: MouseEvent<HTMLTableRowElement>,
        filaId: string,
    ) => {
        const objetivo = evento.target as HTMLElement;

        // Los botones, enlaces y campos de la fila tienen su propio clic; y al seleccionar
        // texto para copiarlo tampoco se abre el menú.
        if (
            objetivo.closest(SELECTOR_INTERACTIVO) ||
            window.getSelection()?.toString()
        ) {
            return;
        }

        setMenu({
            filaId,
            clic: { x: evento.clientX, y: evento.clientY },
            visible: true,
        });
    };

    /** Con el teclado (Enter o espacio sobre la fila) el menú sale bajo el inicio de la fila. */
    const abrirMenuConTeclado = (
        evento: KeyboardEvent<HTMLTableRowElement>,
        filaId: string,
    ) => {
        if (
            evento.target !== evento.currentTarget ||
            (evento.key !== 'Enter' && evento.key !== ' ')
        ) {
            return;
        }

        evento.preventDefault();
        const caja = evento.currentTarget.getBoundingClientRect();

        setMenu({
            filaId,
            clic: { x: caja.left + 120, y: caja.bottom },
            visible: true,
            conTeclado: true,
        });
    };

    const cerrarMenu = useCallback(
        () =>
            setMenu((actual) =>
                actual?.visible ? { ...actual, visible: false } : actual,
            ),
        [],
    );
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

                {eliminarPorLote && seleccionados.length > 0 ? (
                    <div
                        className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/40 px-3 py-1.5 text-sm sm:ml-auto"
                        data-test="data-table-seleccion"
                    >
                        <span className="font-medium">
                            {seleccionados.length} seleccionado(s)
                        </span>
                        {!todosFiltradosElegidos ? (
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                className="h-auto px-1"
                                onClick={() => alternarFiltrados(true)}
                                data-test="data-table-seleccionar-todos"
                            >
                                Seleccionar los {idsFiltrados.length}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setSeleccion(new Set())}
                        >
                            Quitar selección
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setConfirmarLote(true)}
                            data-test="data-table-eliminar-lote"
                        >
                            <Trash2 className="h-4 w-4" /> Eliminar
                        </Button>
                    </div>
                ) : null}
            </div>

            {eliminarPorLote ? (
                <EliminarPorLoteDialog
                    open={confirmarLote}
                    onOpenChange={setConfirmarLote}
                    url={eliminarPorLote.url}
                    ids={seleccionados}
                    nombre={eliminarPorLote.nombre}
                    onEliminados={() => setSeleccion(new Set())}
                />
            ) : null}

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
                                {conSeleccion ? (
                                    <th className="w-10 px-4 py-3">
                                        <Checkbox
                                            checked={
                                                todosFiltradosElegidos
                                                    ? true
                                                    : filtradosElegidos.length >
                                                        0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(marcada) =>
                                                alternarFiltrados(
                                                    marcada === true,
                                                )
                                            }
                                            disabled={idsFiltrados.length === 0}
                                            aria-label="Seleccionar todas las filas filtradas"
                                            data-test="data-table-seleccionar-filtrados"
                                        />
                                    </th>
                                ) : null}
                                {headerGroup.headers
                                    .filter((header) =>
                                        esVisible(header.column.id),
                                    )
                                    .map((header) => {
                                        const canSort =
                                            header.column.getCanSort();
                                        const sorted =
                                            header.column.getIsSorted();

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
                                                        <IconoColumna
                                                            icono={
                                                                header.column
                                                                    .columnDef
                                                                    .meta
                                                                    ?.icono ??
                                                                iconoDeColumna(
                                                                    header
                                                                        .column
                                                                        .columnDef
                                                                        .header,
                                                                )
                                                            }
                                                        />
                                                        <table.FlexRender
                                                            header={header}
                                                        />
                                                        {sorted === 'asc' ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : sorted ===
                                                          'desc' ? (
                                                            <ChevronDown className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronsUpDown className="h-4 w-4 opacity-50" />
                                                        )}
                                                    </button>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1">
                                                        <IconoColumna
                                                            icono={
                                                                header.column
                                                                    .columnDef
                                                                    .meta
                                                                    ?.icono ??
                                                                iconoDeColumna(
                                                                    header
                                                                        .column
                                                                        .columnDef
                                                                        .header,
                                                                )
                                                            }
                                                        />
                                                        <table.FlexRender
                                                            header={header}
                                                        />
                                                    </span>
                                                )}
                                                {header.isPlaceholder ? null : (
                                                    <ColumnResizeHandle
                                                        label={header.column.id}
                                                        onPointerDown={iniciarRedimension(
                                                            header.column.id,
                                                        )}
                                                        onDoubleClick={
                                                            restablecer
                                                        }
                                                    />
                                                )}
                                            </th>
                                        );
                                    })}
                            </tr>
                        ))}
                        <tr className="border-t bg-background">
                            {conSeleccion ? <th className="px-4 py-2" /> : null}
                            {leafHeaders.map((header) => {
                                const column = header.column;
                                const opciones =
                                    column.columnDef.meta?.filterOptions;
                                const valor =
                                    (column.getFilterValue() as
                                        | string
                                        | undefined) ?? '';
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
                            <tr
                                key={row.id}
                                data-test={rowTestId}
                                onClick={
                                    tieneAcciones
                                        ? (evento) => abrirMenu(evento, row.id)
                                        : onFilaClick
                                          ? () => onFilaClick(row.original)
                                          : undefined
                                }
                                tabIndex={tieneAcciones ? 0 : undefined}
                                aria-haspopup={
                                    tieneAcciones ? 'menu' : undefined
                                }
                                onKeyDown={
                                    tieneAcciones
                                        ? (evento) =>
                                              abrirMenuConTeclado(
                                                  evento,
                                                  row.id,
                                              )
                                        : undefined
                                }
                                className={cn(
                                    'transition-colors hover:bg-primary/10',
                                    claseFila?.(row.original),
                                    (tieneAcciones || onFilaClick) &&
                                        'cursor-pointer',
                                    menu?.visible &&
                                        menu.filaId === row.id &&
                                        'bg-primary/10',
                                )}
                            >
                                {conSeleccion ? (
                                    <td
                                        className="w-10 px-4 py-3"
                                        data-sin-menu-fila
                                        onClick={(evento) =>
                                            evento.stopPropagation()
                                        }
                                    >
                                        <Checkbox
                                            checked={seleccion.has(
                                                idDeFila(row.original),
                                            )}
                                            onCheckedChange={(marcada) =>
                                                alternarFila(
                                                    idDeFila(row.original),
                                                    marcada === true,
                                                )
                                            }
                                            aria-label="Seleccionar fila"
                                            data-test="data-table-seleccionar-fila"
                                        />
                                    </td>
                                ) : null}
                                {row
                                    .getAllCells()
                                    .filter((cell) => esVisible(cell.column.id))
                                    .map((cell) => (
                                        <td key={cell.id} className="px-4 py-3">
                                            <table.FlexRender cell={cell} />
                                        </td>
                                    ))}
                            </tr>
                        ))}

                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={clavesColumnas.length}
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

            {menu && celdaAccionesMenu ? (
                <MenuAccionesFila
                    key={menu.filaId}
                    clic={menu.clic}
                    visible={menu.visible}
                    enfocar={menu.conTeclado}
                    onCerrar={cerrarMenu}
                >
                    <table.FlexRender cell={celdaAccionesMenu} />
                </MenuAccionesFila>
            ) : null}

            {totalRows > 0 ? (
                <div className="flex flex-col gap-2 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:gap-4">
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
