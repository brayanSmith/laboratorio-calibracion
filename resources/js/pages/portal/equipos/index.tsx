import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Clock,
    MapPin,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { CLASE_FILA_EQUIPO_INACTIVO } from '@/components/equipo-inactivo-badge';
import {
    CeldaEquipo,
    CeldaUbicacion,
    textoEquipo,
} from '@/components/equipos/celdas-equipo';
import Heading from '@/components/heading';
import AlertasEquipos from '@/components/portal/alertas-equipos';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatearFecha } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import { show } from '@/routes/portal/equipos';
import type { EquipoPortal } from '@/types';

type Props = {
    equipos: EquipoPortal[];
};

type EstadoEquipo = EquipoPortal['estado_vencimiento'];

/** Cómo se muestra cada estado del equipo (los colores de la interfaz). */
const ESTADOS: Record<EstadoEquipo, { etiqueta: string; clase: string }> = {
    AL_DIA: { etiqueta: 'Al día', clase: '!bg-emerald-500' },
    PROXIMO_A_VENCER: { etiqueta: 'Próximo a vencer', clase: '!bg-amber-500' },
    VENCIDO: { etiqueta: 'Vencido', clase: '!bg-red-500' },
    SIN_PROGRAMACION: { etiqueta: 'Sin programación', clase: '!bg-slate-400' },
    INACTIVO: { etiqueta: 'Inactivo', clase: '!bg-slate-500' },
};

/** Las tarjetas de resumen, con los mismos colores e íconos que las del dashboard del laboratorio. */
const TARJETAS: {
    titulo: string;
    icono: LucideIcon;
    color: string;
    /** Botón en un tono más intenso que el fondo suave de la tarjeta. */
    colorBoton: string;
    testId: string;
    /** Estado por el que la tarjeta filtra la tabla; null muestra todos los equipos activos. */
    estado: EstadoEquipo | null;
}[] = [
    {
        titulo: 'Todos los equipos',
        icono: Boxes,
        color: 'border-sky-200 bg-sky-100 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        colorBoton: 'bg-sky-600 text-white hover:bg-sky-700',
        testId: 'portal-tarjeta-todos',
        estado: null,
    },
    {
        titulo: 'Equipos al día',
        icono: CheckCircle2,
        color: 'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        colorBoton: 'bg-emerald-600 text-white hover:bg-emerald-700',
        testId: 'portal-tarjeta-al-dia',
        estado: 'AL_DIA',
    },
    {
        titulo: 'Equipos próximos a vencer',
        icono: Clock,
        color: 'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        colorBoton: 'bg-amber-500 text-white hover:bg-amber-600',
        testId: 'portal-tarjeta-proximos',
        estado: 'PROXIMO_A_VENCER',
    },
    {
        titulo: 'Equipos vencidos',
        icono: AlertTriangle,
        color: 'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        colorBoton: 'bg-red-600 text-white hover:bg-red-700',
        testId: 'portal-tarjeta-vencidos',
        estado: 'VENCIDO',
    },
];

const columnHelper = createDataTableColumnHelper<EquipoPortal>();

/**
 * Los equipos del cliente (los suyos y los de sus bahías) con su estado y su próximo
 * servicio. Al tocar uno se abre su traza.
 */
export default function PortalEquipos({ equipos }: Props) {
    // Cada "Ver" vuelve a montar la tabla ya filtrada por ese estado (como en el dashboard).
    const [vista, setVista] = useState<{
        n: number;
        estado: EstadoEquipo | null;
    }>({ n: 0, estado: null });
    const lista = useRef<HTMLDivElement>(null);

    const verEstado = (estado: EstadoEquipo | null) => {
        setVista((actual) => ({ n: actual.n + 1, estado }));
        // En el celular la lista queda debajo de las tarjetas: se baja hasta ella.
        lista.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const conteo = (estado: EstadoEquipo | null): number =>
        equipos.filter((equipo) =>
            estado === null
                ? equipo.activo
                : equipo.estado_vencimiento === estado,
        ).length;

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor(
                    (row) =>
                        textoEquipo(
                            row.codigo,
                            row.tipo_equipo,
                            row.modelo,
                            row.numero_serie,
                        ),
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <CeldaEquipo
                                codigo={row.original.codigo}
                                tipo={row.original.tipo_equipo}
                                clase={row.original.clase_equipo}
                                modelo={row.original.modelo}
                                serie={row.original.numero_serie}
                                inactivo={!row.original.activo}
                            />
                        ),
                    },
                ),
                columnHelper.accessor(
                    (row) => `${row.area ?? ''} ${row.bahia ?? ''}`,
                    {
                        id: 'ubicacion',
                        header: 'Área / Bahía',
                        cell: ({ row }) => (
                            <CeldaUbicacion
                                area={row.original.area}
                                bahia={row.original.bahia}
                            />
                        ),
                    },
                ),
                columnHelper.accessor('estado_vencimiento', {
                    header: 'Estado',
                    meta: {
                        filterOptions: Object.entries(ESTADOS).map(
                            ([value, { etiqueta }]) => ({
                                value,
                                label: etiqueta,
                            }),
                        ),
                    },
                    cell: (info) => {
                        const estado = ESTADOS[info.getValue()];

                        return (
                            <Badge
                                className={`!border-transparent !text-white ${estado.clase}`}
                                data-test="portal-equipo-estado"
                            >
                                {estado.etiqueta}
                            </Badge>
                        );
                    },
                }),
                columnHelper.accessor('fecha_proximo_servicio', {
                    header: 'Próximo servicio',
                    cell: (info) =>
                        info.getValue()
                            ? formatearFecha(info.getValue()!)
                            : '—',
                }),
            ]),
        [],
    );

    return (
        <>
            <Head title="Mis equipos" />

            <div className="space-y-6">
                <Heading
                    title="Mis equipos"
                    description="El estado de tus equipos y de los de tus bahías. Toca uno para ver su historial y descargar sus certificados e informes."
                />

                <AlertasEquipos equipos={equipos} />

                {/* Dos por fila en el celular, más compactas; cuatro en pantallas grandes. */}
                <div className="grid auto-rows-min grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                    {TARJETAS.map((tarjeta) => (
                        <Card
                            key={tarjeta.testId}
                            data-test={tarjeta.testId}
                            className={cn(
                                'gap-2 py-4 sm:gap-6 sm:py-6',
                                tarjeta.color,
                            )}
                        >
                            <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0 px-4 sm:px-6">
                                <CardTitle className="text-xs leading-snug font-medium sm:text-sm">
                                    {tarjeta.titulo}
                                </CardTitle>
                                <tarjeta.icono className="size-4 shrink-0 sm:size-5" />
                            </CardHeader>
                            <CardContent className="flex items-end justify-between gap-2 px-4 sm:px-6">
                                <p className="text-2xl font-bold tabular-nums sm:text-3xl">
                                    {conteo(tarjeta.estado)}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    className={cn(
                                        'h-7 px-2.5 sm:h-8 sm:px-3',
                                        tarjeta.colorBoton,
                                    )}
                                    onClick={() => verEstado(tarjeta.estado)}
                                    data-test={`${tarjeta.testId}-ver`}
                                >
                                    Ver
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div ref={lista} className="scroll-mt-20 md:hidden">
                    <ListaEquiposMovil
                        key={vista.n}
                        equipos={equipos}
                        estadoInicial={vista.estado}
                    />
                </div>

                <div className="hidden rounded-lg border bg-background p-4 md:block">
                    <DataTable
                        key={vista.n}
                        initialColumnFilters={
                            vista.estado
                                ? [
                                      {
                                          id: 'estado_vencimiento',
                                          value: vista.estado,
                                      },
                                  ]
                                : undefined
                        }
                        data={equipos}
                        columns={columns}
                        pageSize={15}
                        rowTestId="portal-equipo-row"
                        searchPlaceholder="Buscar equipos..."
                        emptyMessage="Todavía no tienes equipos registrados."
                        onFilaClick={(equipo) =>
                            router.visit(show(equipo.id).url)
                        }
                        claseFila={(equipo) =>
                            equipo.activo
                                ? undefined
                                : CLASE_FILA_EQUIPO_INACTIVO
                        }
                    />
                </div>
            </div>
        </>
    );
}

/** Cuántos equipos se muestran en el celular antes de "Mostrar más". */
const POR_TANDA = 20;

/** Filtros rápidos de la lista del celular, en orden de urgencia. */
const FILTROS_MOVIL: { valor: EstadoEquipo | null; etiqueta: string }[] = [
    { valor: null, etiqueta: 'Todos' },
    { valor: 'VENCIDO', etiqueta: 'Vencidos' },
    { valor: 'PROXIMO_A_VENCER', etiqueta: 'Próximos' },
    { valor: 'AL_DIA', etiqueta: 'Al día' },
    { valor: 'SIN_PROGRAMACION', etiqueta: 'Sin programación' },
    { valor: 'INACTIVO', etiqueta: 'Inactivos' },
];

/**
 * Los equipos como tarjetas, para el celular (la tabla obligaría a desplazarse de lado):
 * buscador, filtros por estado y "Mostrar más" en lugar de páginas.
 */
function ListaEquiposMovil({
    equipos,
    estadoInicial,
}: {
    equipos: EquipoPortal[];
    estadoInicial: EstadoEquipo | null;
}) {
    const [busqueda, setBusqueda] = useState('');
    const [estado, setEstado] = useState<EstadoEquipo | null>(estadoInicial);
    const [mostrados, setMostrados] = useState(POR_TANDA);

    const termino = busqueda.trim().toLowerCase();
    const filtrados = equipos.filter(
        (equipo) =>
            (estado === null || equipo.estado_vencimiento === estado) &&
            (termino === '' ||
                [
                    equipo.codigo,
                    equipo.tipo_equipo,
                    equipo.modelo,
                    equipo.numero_serie,
                    equipo.area,
                    equipo.bahia,
                ].some((dato) => dato?.toLowerCase().includes(termino))),
    );

    return (
        <div className="space-y-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={busqueda}
                    onChange={(evento) => {
                        setBusqueda(evento.target.value);
                        setMostrados(POR_TANDA);
                    }}
                    placeholder="Buscar por código, modelo, serie o bahía"
                    className="bg-background pl-9"
                    data-test="portal-buscar-movil"
                />
            </div>

            {/* Chips deslizables de lado, sin salirse de la pantalla. */}
            <div className="-mx-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 pb-1">
                {FILTROS_MOVIL.map(({ valor, etiqueta }) => (
                    <button
                        key={etiqueta}
                        type="button"
                        onClick={() => {
                            setEstado(valor);
                            setMostrados(POR_TANDA);
                        }}
                        className={cn(
                            'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                            estado === valor
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background text-muted-foreground',
                        )}
                    >
                        {etiqueta}
                    </button>
                ))}
            </div>

            {filtrados.length === 0 ? (
                <p className="rounded-lg border bg-background p-6 text-center text-sm text-muted-foreground">
                    {equipos.length === 0
                        ? 'Todavía no tienes equipos registrados.'
                        : 'Ningún equipo coincide con la búsqueda.'}
                </p>
            ) : (
                <ul className="space-y-2">
                    {filtrados.slice(0, mostrados).map((equipo) => {
                        const estadoEquipo = ESTADOS[equipo.estado_vencimiento];

                        return (
                            <li key={equipo.id}>
                                <Link
                                    href={show(equipo.id).url}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border bg-background p-3 active:bg-muted',
                                        !equipo.activo && 'opacity-70',
                                    )}
                                    data-test="portal-equipo-movil"
                                >
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <p
                                                className={cn(
                                                    'truncate font-semibold',
                                                    !equipo.activo &&
                                                        'line-through',
                                                )}
                                            >
                                                {equipo.codigo}
                                            </p>
                                            <Badge
                                                className={`shrink-0 !border-transparent !text-white ${estadoEquipo.clase}`}
                                            >
                                                {estadoEquipo.etiqueta}
                                            </Badge>
                                        </div>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {[equipo.tipo_equipo, equipo.modelo]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </p>
                                        <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                            {equipo.bahia || equipo.area ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin className="size-3" />
                                                    {equipo.bahia ??
                                                        equipo.area}
                                                </span>
                                            ) : null}
                                            {equipo.fecha_proximo_servicio ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <CalendarClock className="size-3" />
                                                    {formatearFecha(
                                                        equipo.fecha_proximo_servicio,
                                                    )}
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}

            {filtrados.length > mostrados ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={() => setMostrados((actual) => actual + POR_TANDA)}
                >
                    Mostrar más ({filtrados.length - mostrados} restantes)
                </Button>
            ) : null}
        </div>
    );
}
