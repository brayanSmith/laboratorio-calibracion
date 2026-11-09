import { useHttp } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import Combobox from '@/components/combobox';
import MultiCombobox from '@/components/multi-combobox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    depurarFiltrosDependientes,
    filtrosConValor,
    formatearPorcentaje,
    PERIODO_POR_DEFECTO,
    periodosIndicador,
    rangoDePeriodo,
} from '@/lib/indicadores';
import type {
    OpcionesDependientes,
    PeriodoIndicador,
    ValorFiltro,
} from '@/lib/indicadores';
import { cn } from '@/lib/utils';

/**
 * Pide los datos de un indicador cada vez que cambian sus filtros. `ruta` arma la URL
 * con los filtros (una ruta de Wayfinder).
 */
export function useIndicador<T>(
    ruta: (opciones: { query: Record<string, string | string[]> }) => {
        url: string;
    },
    filtros: { desde: string; hasta: string } & Record<string, ValorFiltro>,
) {
    const { get, response, processing } = useHttp<Record<string, string>, T>();
    const clave = JSON.stringify(filtros);
    const recarga = useContext(RecargaIndicadorContext);

    useEffect(() => {
        if (
            filtros.desde === '' ||
            filtros.hasta === '' ||
            filtros.hasta < filtros.desde
        ) {
            return;
        }

        void get(ruta({ query: filtrosConValor(filtros) }).url).catch(
            () => undefined,
        );
        // Solo se vuelve a pedir si cambian los filtros o se guardan objetivos del módulo.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clave, recarga]);

    return { datos: response, cargando: processing };
}

/**
 * Filtros dependientes de selección múltiple de un indicador: devuelve las opciones de
 * cada lista (las que llegaron con los datos; mientras se recalcula se siguen mostrando las
 * anteriores) y quita de los filtros lo elegido que ya no está en su lista.
 */
export function useFiltrosDependientes<
    T extends Record<string, ValorFiltro>,
    O extends OpcionesDependientes,
>(
    opcionesNuevas: O | undefined,
    filtros: T,
    setFiltros: (filtros: T) => void,
    sinOpciones: O,
): O {
    const [opciones, setOpciones] = useState<O>(sinOpciones);

    useEffect(() => {
        if (!opcionesNuevas) {
            return;
        }

        setOpciones(opcionesNuevas);

        const depurados = depurarFiltrosDependientes(filtros, opcionesNuevas);

        if (depurados) {
            setFiltros(depurados);
        }
        // Solo cuando llegan opciones nuevas.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [opcionesNuevas]);

    return opciones;
}

/** Opciones `{id, nombre}` de la respuesta en el formato de los selectores. */
export const aOpcionesDependientes = (
    opciones: { id: number | string; nombre: string }[],
) =>
    opciones.map((opcion) => ({ id: String(opcion.id), label: opcion.nombre }));

/**
 * Versión de los datos de un módulo: sube cuando se guardan sus objetivos, para que el
 * indicador se vuelva a pedir y sus gráficos muestren la línea nueva.
 */
export const RecargaIndicadorContext = createContext(0);

/** Tonos de las tarjetas, los mismos de las tarjetas de la pestaña Resumen. */
const TONOS = {
    sky: {
        tarjeta:
            'border-sky-200 bg-sky-100 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        barra: 'bg-sky-600',
        pista: 'bg-sky-600/15',
    },
    emerald: {
        tarjeta:
            'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        barra: 'bg-emerald-600',
        pista: 'bg-emerald-600/15',
    },
    amber: {
        tarjeta:
            'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        barra: 'bg-amber-500',
        pista: 'bg-amber-500/15',
    },
    red: {
        tarjeta:
            'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        barra: 'bg-red-600',
        pista: 'bg-red-600/15',
    },
    violet: {
        tarjeta:
            'border-violet-200 bg-violet-100 text-violet-900 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200',
        barra: 'bg-violet-600',
        pista: 'bg-violet-600/15',
    },
} as const;

export type TonoIndicador = keyof typeof TONOS;

/**
 * Tono según el porcentaje de cumplimiento: verde desde el 90 %, amarillo desde el 70 %
 * y rojo por debajo.
 */
export function tonoDePorcentaje(porcentaje: number | null): TonoIndicador {
    if (porcentaje === null) {
        return 'sky';
    }

    if (porcentaje >= 90) {
        return 'emerald';
    }

    return porcentaje >= 70 ? 'amber' : 'red';
}

/**
 * Un número destacado del indicador, con el mismo diseño de las tarjetas de Resumen:
 * fondo suave del tono, título con su icono y el valor grande.
 */
export function StatTile({
    etiqueta,
    valor,
    detalle,
    icono: Icono,
    tono,
    testId,
    children,
}: {
    etiqueta: string;
    valor: string;
    detalle?: string;
    icono: LucideIcon;
    tono: TonoIndicador;
    testId?: string;
    children?: ReactNode;
}) {
    return (
        <Card className={TONOS[tono].tarjeta} data-test={testId}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="text-sm font-medium">
                    {etiqueta}
                </CardTitle>
                <Icono className="h-5 w-5" />
            </CardHeader>
            <CardContent className="grid gap-1">
                <p className="text-3xl font-bold">{valor}</p>
                {detalle ? (
                    <p className="text-xs opacity-80">{detalle}</p>
                ) : null}
                {children}
            </CardContent>
        </Card>
    );
}

/** Panel de filtros de un indicador, sobre un fondo suave para separarlo de los datos. */
export function PanelFiltros({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-xl border bg-muted/40 p-4">
            <div className="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <SlidersHorizontal className="h-4 w-4" />
                Filtros
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {children}
            </div>
        </div>
    );
}

/** Tarjeta con título e icono para un bloque de detalle (tabla, avance por área...). */
export function BloqueIndicador({
    titulo,
    icono: Icono,
    children,
}: {
    titulo: string;
    icono: LucideIcon;
    children: ReactNode;
}) {
    return (
        <Card className="min-w-0">
            <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                <Icono className="h-5 w-5 text-muted-foreground" />
                <CardTitle className="text-base">{titulo}</CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/**
 * Barra de avance (0 a 100 %) sobre una pista del mismo tono más claro, con el
 * porcentaje escrito al lado para que no dependa solo del color.
 */
export function Medidor({
    porcentaje,
    etiqueta,
    tono,
    sinTexto = false,
    className,
}: {
    porcentaje: number | null;
    etiqueta: string;
    tono?: TonoIndicador;
    /** Oculta el porcentaje escrito, cuando ya se muestra al lado (como en una tarjeta). */
    sinTexto?: boolean;
    className?: string;
}) {
    const valor = Math.min(100, Math.max(0, porcentaje ?? 0));
    const colores = tono
        ? TONOS[tono]
        : { barra: 'bg-primary', pista: 'bg-primary/15' };

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <div
                role="meter"
                aria-label={etiqueta}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={valor}
                className={cn(
                    'h-2.5 flex-1 overflow-hidden rounded-full',
                    colores.pista,
                )}
            >
                <div
                    className={cn('h-full rounded-full', colores.barra)}
                    style={{ width: `${valor}%` }}
                />
            </div>
            {sinTexto ? null : (
                <span className="w-14 text-right text-sm font-medium tabular-nums">
                    {formatearPorcentaje(porcentaje)}
                </span>
            )}
        </div>
    );
}

/**
 * Periodo de los filtros de un indicador (hoy, ayer, última semana...). Con
 * "Personalizado" aparecen las fechas desde y hasta para elegir el rango.
 */
export function FiltroRango({
    id,
    desde,
    hasta,
    onChange,
}: {
    id: string;
    desde: string;
    hasta: string;
    onChange: (rango: { desde: string; hasta: string }) => void;
}) {
    const [periodo, setPeriodo] =
        useState<PeriodoIndicador>(PERIODO_POR_DEFECTO);

    const cambiarPeriodo = (valor: string) => {
        const nuevo = valor as PeriodoIndicador;
        const rango = rangoDePeriodo(nuevo);
        setPeriodo(nuevo);

        if (rango) {
            onChange(rango);
        }
    };

    return (
        <>
            <div className="grid gap-1">
                <Label htmlFor={`${id}-periodo`} className="text-xs">
                    Periodo
                </Label>
                <Combobox
                    id={`${id}-periodo`}
                    value={periodo}
                    onValueChange={cambiarPeriodo}
                    options={periodosIndicador.map((opcion) => ({
                        id: opcion.value,
                        label: opcion.label,
                    }))}
                />
            </div>
            {periodo === 'personalizado' ? (
                <FechasPersonalizadas
                    id={id}
                    desde={desde}
                    hasta={hasta}
                    onChange={onChange}
                />
            ) : null}
        </>
    );
}

function FechasPersonalizadas({
    id,
    desde,
    hasta,
    onChange,
}: {
    id: string;
    desde: string;
    hasta: string;
    onChange: (rango: { desde: string; hasta: string }) => void;
}) {
    return (
        <>
            <div className="grid gap-1">
                <Label htmlFor={`${id}-desde`} className="text-xs">
                    Desde
                </Label>
                <Input
                    id={`${id}-desde`}
                    type="date"
                    value={desde}
                    max={hasta || undefined}
                    onChange={(event) =>
                        onChange({ desde: event.target.value, hasta })
                    }
                />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`${id}-hasta`} className="text-xs">
                    Hasta
                </Label>
                <Input
                    id={`${id}-hasta`}
                    type="date"
                    value={hasta}
                    min={desde || undefined}
                    onChange={(event) =>
                        onChange({ desde, hasta: event.target.value })
                    }
                />
            </div>
        </>
    );
}

/**
 * Un filtro de selección múltiple del indicador; sin nada elegido no filtra ("Todos").
 */
export function FiltroSeleccionMultiple({
    id,
    etiqueta,
    value,
    onChange,
    opciones,
    todos = 'Todos',
    plural,
}: {
    id: string;
    etiqueta: string;
    value: string[];
    onChange: (value: string[]) => void;
    opciones: { id: number | string; label: string }[];
    todos?: string;
    /** Cómo se llama a varias opciones elegidas, ej. "áreas". */
    plural: string;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                {etiqueta}
            </Label>
            <MultiCombobox
                id={id}
                value={value}
                onValueChange={onChange}
                options={opciones}
                placeholder={todos}
                searchPlaceholder={`Buscar ${etiqueta.toLowerCase()}...`}
                plural={plural}
            />
        </div>
    );
}

/**
 * Casillas para elegir qué se incluye (ej. mantenimiento y/o calibración, tipo A y/o B).
 * Siempre queda al menos una marcada: la última no se puede quitar.
 */
export function FiltroCasillas({
    id,
    etiqueta,
    opciones,
    value,
    onChange,
}: {
    id: string;
    etiqueta: string;
    opciones: { value: string; label: string }[];
    value: string[];
    onChange: (value: string[]) => void;
}) {
    const alternar = (opcion: string, marcada: boolean) => {
        const elegidas = marcada
            ? [...value, opcion]
            : value.filter((actual) => actual !== opcion);

        if (elegidas.length > 0) {
            // En el orden de las casillas.
            onChange(
                opciones
                    .map((item) => item.value)
                    .filter((item) => elegidas.includes(item)),
            );
        }
    };

    return (
        <fieldset className="grid gap-1">
            <legend className="mb-1 text-xs font-medium">{etiqueta}</legend>
            <div className="flex h-9 items-center gap-4">
                {opciones.map((opcion) => (
                    <label
                        key={opcion.value}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={value.includes(opcion.value)}
                            onCheckedChange={(marcada) =>
                                alternar(opcion.value, marcada === true)
                            }
                            disabled={
                                value.length === 1 &&
                                value.includes(opcion.value)
                            }
                            data-test={`${id}-${opcion.value}`}
                        />
                        {opcion.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/**
 * Filtro de una sola opción entre pocas (ej. Local / Tercero), como botones unidos;
 * siempre queda una elegida.
 */
export function FiltroAlternativa<T extends string>({
    id,
    etiqueta,
    opciones,
    value,
    onChange,
}: {
    id: string;
    etiqueta: string;
    opciones: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
}) {
    return (
        <fieldset className="grid gap-1">
            <legend className="mb-1 text-xs font-medium">{etiqueta}</legend>
            <ToggleGroup
                type="single"
                variant="outline"
                value={value}
                onValueChange={(elegida) => {
                    if (elegida) {
                        onChange(elegida as T);
                    }
                }}
                className="h-9"
                data-test={id}
            >
                {opciones.map((opcion) => (
                    <ToggleGroupItem
                        key={opcion.value}
                        value={opcion.value}
                        className="px-4 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                        data-test={`${id}-${opcion.value}`}
                    >
                        {opcion.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </fieldset>
    );
}
