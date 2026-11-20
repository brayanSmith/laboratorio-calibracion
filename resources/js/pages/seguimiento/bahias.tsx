import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheckBig,
    CircleX,
    Gauge,
    Hourglass,
    Layers,
    MapPin,
    PackageCheck,
    PackageSearch,
    Search,
    Truck,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import DataTable from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import {
    columnasSeguimiento,
    etapaActual,
    HistorialEquipo,
    PiePagina,
    TarjetaEquipo,
} from './show';
import type { ContactoEmpresa, EquipoSeguimiento } from './show';

type BahiaCliente = {
    id: number;
    nombre: string;
    area: string | null;
};

type Props = {
    cliente: string | null;
    empresa: { nombre: string; logo_url: string | null } | null;
    contacto: ContactoEmpresa | null;
    bahias: BahiaCliente[];
    /** Los equipos de todos sus ingresos, del ingreso más reciente al más antiguo. */
    equipos: EquipoSeguimiento[];
};

/** Estado de control de un equipo, para las tarjetas que filtran. */
type Control =
    | 'todos'
    | 'pendientes'
    | 'En bahía'
    | 'Ingreso'
    | 'Mantenimiento'
    | 'Calibración'
    | 'Despacho'
    | 'Entregado'
    | 'No ingresó';

/**
 * Tarjetas de estado, en el orden del recorrido. "Pendientes" junta todo lo que aún no se
 * entrega, que es lo que el cliente quiere tener bajo control.
 */
const TARJETAS: {
    clave: Control;
    titulo: string;
    icono: LucideIcon;
    color: string;
    /** Fondo, borde y anillo de la tarjeta elegida, del tono de su ícono. */
    seleccionada: string;
    /** El recuadro del ícono, lleno de ese tono, en la tarjeta elegida. */
    iconoSeleccionado: string;
}[] = [
    {
        clave: 'todos',
        titulo: 'Todos',
        icono: Layers,
        color: 'text-foreground',
        seleccionada: 'border-foreground bg-foreground/5 ring-foreground/15',
        iconoSeleccionado: 'bg-foreground text-background',
    },
    {
        clave: 'pendientes',
        titulo: 'Pendientes',
        icono: Hourglass,
        color: 'text-amber-600 dark:text-amber-400',
        seleccionada:
            'border-amber-500 bg-amber-50 ring-amber-500/25 dark:bg-amber-950/40',
        iconoSeleccionado: 'bg-amber-500 text-white',
    },
    {
        clave: 'En bahía',
        titulo: 'En bahía',
        icono: MapPin,
        color: 'text-slate-600 dark:text-slate-300',
        seleccionada:
            'border-slate-500 bg-slate-100 ring-slate-500/25 dark:bg-slate-800/60',
        iconoSeleccionado: 'bg-slate-500 text-white',
    },
    {
        clave: 'Ingreso',
        titulo: 'Ingreso',
        icono: PackageCheck,
        color: 'text-sky-600 dark:text-sky-400',
        seleccionada:
            'border-sky-500 bg-sky-50 ring-sky-500/25 dark:bg-sky-950/40',
        iconoSeleccionado: 'bg-sky-500 text-white',
    },
    {
        clave: 'Mantenimiento',
        titulo: 'Mantenimiento',
        icono: Wrench,
        color: 'text-amber-600 dark:text-amber-400',
        seleccionada:
            'border-amber-500 bg-amber-50 ring-amber-500/25 dark:bg-amber-950/40',
        iconoSeleccionado: 'bg-amber-500 text-white',
    },
    {
        clave: 'Calibración',
        titulo: 'Calibración',
        icono: Gauge,
        color: 'text-violet-600 dark:text-violet-400',
        seleccionada:
            'border-violet-500 bg-violet-50 ring-violet-500/25 dark:bg-violet-950/40',
        iconoSeleccionado: 'bg-violet-500 text-white',
    },
    {
        clave: 'Despacho',
        titulo: 'Despacho',
        icono: Truck,
        color: 'text-emerald-600 dark:text-emerald-400',
        seleccionada:
            'border-emerald-500 bg-emerald-50 ring-emerald-500/25 dark:bg-emerald-950/40',
        iconoSeleccionado: 'bg-emerald-500 text-white',
    },
    {
        clave: 'Entregado',
        titulo: 'Entregados',
        icono: CircleCheckBig,
        color: 'text-emerald-600 dark:text-emerald-400',
        seleccionada:
            'border-emerald-600 bg-emerald-50 ring-emerald-600/25 dark:bg-emerald-950/40',
        iconoSeleccionado: 'bg-emerald-600 text-white',
    },
    {
        clave: 'No ingresó',
        titulo: 'No ingresaron',
        icono: CircleX,
        color: 'text-red-600 dark:text-red-400',
        seleccionada:
            'border-red-500 bg-red-50 ring-red-500/25 dark:bg-red-950/40',
        iconoSeleccionado: 'bg-red-500 text-white',
    },
];

/** En qué va el equipo: su etapa actual, o si ya se entregó o no ingresó. */
function controlDe(equipo: EquipoSeguimiento): Control {
    const ultimo = equipo.historial.at(-1);

    if (equipo.ingresado === false) {
        return 'No ingresó';
    }

    if (ultimo?.fase === 'Despacho' && ultimo.estado === 'Entregado') {
        return 'Entregado';
    }

    return etapaActual(equipo) as Control;
}

function coincideControl(equipo: EquipoSeguimiento, control: Control): boolean {
    const suyo = controlDe(equipo);

    if (control === 'todos') {
        return true;
    }

    if (control === 'pendientes') {
        return suyo !== 'Entregado' && suyo !== 'No ingresó';
    }

    return suyo === control;
}

const TODAS = 0;

/**
 * Seguimiento por bahía: con el enlace que le llegó por correo, el cliente ve en una sola
 * tabla los equipos de todos los ingresos de las bahías que tiene asignadas, del más
 * reciente al más antiguo, y los filtra por estado y por bahía para controlar lo pendiente.
 */
export default function SeguimientoBahias({
    cliente,
    empresa,
    contacto,
    bahias,
    equipos,
}: Props) {
    const { plataforma } = usePage().props;
    const marca = empresa ?? plataforma;
    const [control, setControl] = useState<Control>('pendientes');
    const [bahia, setBahia] = useState<number>(TODAS);
    const [busqueda, setBusqueda] = useState('');
    const [detalle, setDetalle] = useState<EquipoSeguimiento | null>(null);

    const columnas = useMemo(() => columnasSeguimiento(setDetalle, true), []);

    const deLaBahia = equipos.filter(
        (equipo) => bahia === TODAS || equipo.bahia_id === bahia,
    );
    const visibles = deLaBahia.filter((equipo) =>
        coincideControl(equipo, control),
    );
    const conteo = (clave: Control) =>
        deLaBahia.filter((equipo) => coincideControl(equipo, clave)).length;

    const termino = busqueda.trim().toLowerCase();
    const visiblesMovil = visibles.filter(
        (equipo) =>
            termino === '' ||
            [
                equipo.codigo,
                equipo.tipo_equipo,
                equipo.modelo,
                equipo.numero_serie,
                equipo.ingreso,
            ].some((valor) => valor?.toLowerCase().includes(termino)),
    );

    return (
        <div className="flex min-h-svh flex-col bg-muted/40">
            <Head title="Seguimiento por bahía">
                <link
                    head-key="favicon"
                    rel="icon"
                    href={marca.logo_url ?? '/favicon.svg'}
                />
                <link
                    head-key="apple-touch-icon"
                    rel="apple-touch-icon"
                    href={marca.logo_url ?? '/apple-touch-icon.png'}
                />
            </Head>

            <header className="border-b bg-background">
                <div className="flex items-center justify-between gap-3 px-4 py-3 md:px-8 md:py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        {marca.logo_url ? (
                            <img
                                src={marca.logo_url}
                                alt={`Logo de ${marca.nombre}`}
                                className="h-9 max-w-28 shrink-0 rounded-md bg-white object-contain sm:h-10 sm:max-w-40"
                            />
                        ) : (
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground sm:size-10">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                        )}
                        <span className="truncate font-semibold">
                            {marca.nombre}
                        </span>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="shrink-0"
                        asChild
                    >
                        <Link href={home()}>
                            <ArrowLeft />
                            <span className="sr-only sm:not-sr-only">
                                Volver al inicio
                            </span>
                        </Link>
                    </Button>
                </div>
            </header>

            <main className="grid flex-1 grid-cols-[minmax(0,1fr)] content-start gap-4 px-3 py-4 sm:gap-6 sm:px-4 sm:py-8 md:px-8">
                <section className="rounded-xl border bg-background p-4 shadow-xs sm:p-6">
                    <h1 className="text-lg font-semibold tracking-tight sm:text-xl">
                        Seguimiento por bahía
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {cliente ? (
                            <>
                                Hola,{' '}
                                <span className="font-medium text-foreground">
                                    {cliente}
                                </span>
                                .{' '}
                            </>
                        ) : null}
                        Estos son los equipos de las bahías que tienes
                        asignadas, del ingreso más reciente al más antiguo.
                    </p>
                </section>

                {bahias.length === 0 ? (
                    <section
                        className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-background p-6 text-center sm:p-10"
                        data-test="seguimiento-bahias-vacio"
                    >
                        <PackageSearch className="size-8 text-muted-foreground" />
                        <p className="font-medium">
                            Ya no tienes bahías asignadas
                        </p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Comunícate con el laboratorio si crees que es un
                            error.
                        </p>
                    </section>
                ) : (
                    <>
                        {/* Tarjetas de estado: filtran la tabla. */}
                        <section
                            className="-mx-3 flex snap-x gap-2 overflow-x-auto px-3 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
                            aria-label="Filtrar por estado"
                        >
                            {TARJETAS.filter(
                                ({ clave }) =>
                                    clave === 'todos' ||
                                    clave === 'pendientes' ||
                                    conteo(clave) > 0,
                            ).map(
                                ({
                                    clave,
                                    titulo,
                                    icono: Icono,
                                    color,
                                    seleccionada,
                                    iconoSeleccionado,
                                }) => (
                                    <button
                                        key={clave}
                                        type="button"
                                        onClick={() => setControl(clave)}
                                        aria-pressed={control === clave}
                                        className={cn(
                                            'flex min-w-40 shrink-0 snap-start items-center gap-3 rounded-xl border bg-background p-3 text-left shadow-xs transition hover:bg-muted/50 sm:flex-1',
                                            control === clave &&
                                                cn('ring-2', seleccionada),
                                        )}
                                        data-test="seguimiento-bahias-estado"
                                    >
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors',
                                                control === clave
                                                    ? iconoSeleccionado
                                                    : cn('bg-muted', color),
                                            )}
                                        >
                                            <Icono className="size-5" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-xl leading-tight font-semibold tabular-nums">
                                                {conteo(clave)}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {titulo}
                                            </span>
                                        </span>
                                    </button>
                                ),
                            )}
                        </section>

                        <section
                            className="grid grid-cols-[minmax(0,1fr)] gap-3 rounded-xl border bg-background p-4 shadow-xs sm:p-5"
                            data-test="seguimiento-bahias-equipos"
                        >
                            {bahias.length > 1 ? (
                                <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
                                    {[
                                        {
                                            id: TODAS,
                                            nombre: 'Todas las bahías',
                                            area: null,
                                        },
                                        ...bahias,
                                    ].map((opcion) => (
                                        <Button
                                            key={opcion.id}
                                            type="button"
                                            size="sm"
                                            variant={
                                                bahia === opcion.id
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="h-7 shrink-0 rounded-full px-3 text-xs"
                                            onClick={() => setBahia(opcion.id)}
                                            title={opcion.area ?? undefined}
                                            data-test="seguimiento-bahia-filtro"
                                        >
                                            {opcion.id !== TODAS ? (
                                                <MapPin className="size-3.5" />
                                            ) : null}
                                            {opcion.nombre}
                                        </Button>
                                    ))}
                                </div>
                            ) : null}

                            {/* En el celular, tarjetas con su buscador. */}
                            <div className="grid gap-2 md:hidden">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={busqueda}
                                        onChange={(evento) =>
                                            setBusqueda(evento.target.value)
                                        }
                                        placeholder="Buscar equipo o ingreso"
                                        className="pl-8"
                                        aria-label="Buscar equipo"
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {visiblesMovil.length} equipos
                                </p>
                                <ul className="grid gap-2">
                                    {visiblesMovil.map((equipo) => (
                                        <TarjetaEquipo
                                            key={
                                                equipo.id ??
                                                `${equipo.ingreso}-${equipo.codigo}`
                                            }
                                            equipo={equipo}
                                            onVerHistorial={() =>
                                                setDetalle(equipo)
                                            }
                                        />
                                    ))}
                                    {visiblesMovil.length === 0 ? (
                                        <li className="rounded-lg border px-3 py-8 text-center text-sm text-muted-foreground">
                                            No hay equipos en este estado.
                                        </li>
                                    ) : null}
                                </ul>
                            </div>

                            {/* En pantallas grandes, la tabla de la app: se ordena y filtra
                                por columna. */}
                            <div className="hidden md:block">
                                <DataTable
                                    key={`${control}-${bahia}`}
                                    data={visibles}
                                    columns={columnas}
                                    searchPlaceholder="Buscar equipo, ingreso o bahía"
                                    emptyMessage="No hay equipos en este estado."
                                    pageSize={25}
                                    rowTestId="seguimiento-equipo"
                                />
                            </div>
                        </section>
                    </>
                )}
            </main>

            <PiePagina nombre={marca.nombre} contacto={contacto} />

            <HistorialEquipo
                equipo={detalle}
                onClose={() => setDetalle(null)}
            />
        </div>
    );
}
