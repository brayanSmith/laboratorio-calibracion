import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    Gauge,
    History,
    Lock,
    Check,
    CircleX,
    Mail,
    MapPin,
    Phone,
    PackageCheck,
    PackageSearch,
    Route,
    Search,
    Truck,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LineaDeTiempo, Paso } from '@/components/linea-de-tiempo';
import type { TonoPaso } from '@/components/linea-de-tiempo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { colorEstadoIngreso, estadosIngreso } from '@/lib/estados-ingreso';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import { home, seguimiento } from '@/routes';
import type { EstadoIngreso } from '@/types';

type PasoSeguimiento = {
    fase: string;
    estado: string | null;
    fecha: string | null;
    /** Dónde se hace, en Mantenimiento y Calibración (propio o externo). */
    laboratorio: string | null;
};

type EquipoSeguimiento = {
    codigo: string;
    tipo_equipo: string;
    modelo: string | null;
    numero_serie: string | null;
    tipo_servicio: string;
    /** null mientras el ingreso no se ha recibido. */
    ingresado: boolean | null;
    /** Fecha y hora en que se recibió el equipo; null si aún no se recibe. */
    fecha_ingreso: string | null;
    historial: PasoSeguimiento[];
    /** Etapas que le faltan, en orden; se muestran bloqueadas hasta que pasen. */
    siguientes: string[];
};

type IngresoSeguimiento = {
    codigo: string;
    empresa: { nombre: string; logo_url: string | null } | null;
    cliente: string | null;
    bahia: string;
    estado_ingreso: EstadoIngreso;
    fecha_recepcion: string | null;
    /** Fecha y hora en que llegaron los equipos (la del primero recibido). */
    fecha_ingreso: string | null;
    equipos: EquipoSeguimiento[];
};

type Props = {
    codigo: string;
    ingreso: IngresoSeguimiento | null;
    /** Contacto del laboratorio para el pie de página. */
    contacto: ContactoEmpresa | null;
};

type ContactoEmpresa = {
    nombre: string;
    nit: string;
    direccion: string;
    telefono: string;
    email: string | null;
};

const TODAS = 'todas';

/** Un color por etapa, para distinguirlas de un vistazo en la tabla. */
const COLOR_ETAPA: Record<string, string> = {
    'En bahía':
        'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    Ingreso:
        'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    Mantenimiento:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    Calibración:
        'border-transparent bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    Despacho:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
};

/**
 * Consulta pública del seguimiento de un ingreso: con el código que le llegó por correo,
 * el cliente ve en qué va cada equipo sin iniciar sesión.
 */
export default function SeguimientoShow({ codigo, ingreso, contacto }: Props) {
    const { plataforma } = usePage().props;
    const [valor, setValor] = useState(codigo);
    const [buscando, setBuscando] = useState(false);

    function buscar(evento: React.FormEvent) {
        evento.preventDefault();

        router.get(
            seguimiento.url({ query: { codigo: valor.trim() } }),
            {},
            {
                preserveScroll: true,
                onStart: () => setBuscando(true),
                onFinish: () => setBuscando(false),
            },
        );
    }

    const marca = ingreso?.empresa ?? plataforma;

    return (
        <div className="flex min-h-svh flex-col bg-muted/40">
            <Head title="Seguimiento de equipos" />

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

            <main className="grid flex-1 content-start gap-4 px-3 py-4 sm:gap-6 sm:px-4 sm:py-8 md:px-8">
                <section className="rounded-xl border bg-background p-4 shadow-xs sm:p-6">
                    <h1 className="text-lg font-semibold tracking-tight sm:text-xl">
                        Seguimiento de equipos
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ingresa el código del ingreso que te llegó por correo
                        para ver el estado de tus equipos.
                    </p>

                    <form
                        onSubmit={buscar}
                        className="mt-4 flex flex-col gap-3 sm:mt-5 sm:flex-row sm:items-end"
                    >
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="codigo">Código del ingreso</Label>
                            <Input
                                id="codigo"
                                name="codigo"
                                value={valor}
                                onChange={(evento) =>
                                    setValor(evento.target.value.toUpperCase())
                                }
                                placeholder="ING-XXXXXXXX"
                                autoComplete="off"
                                autoFocus={!codigo}
                                required
                                className="font-mono tracking-wider"
                                data-test="seguimiento-codigo"
                            />
                        </div>
                        <Button
                            type="submit"
                            loading={buscando}
                            className="w-full sm:w-auto"
                            data-test="seguimiento-buscar"
                        >
                            {buscando ? null : <Search />}
                            Consultar
                        </Button>
                    </form>
                </section>

                {codigo && !ingreso ? (
                    <section
                        className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-background p-6 text-center sm:p-10"
                        data-test="seguimiento-no-encontrado"
                    >
                        <PackageSearch className="size-8 text-muted-foreground" />
                        <p className="font-medium">
                            No encontramos un ingreso con el código{' '}
                            <span className="font-mono">{codigo}</span>
                        </p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Revisa que esté escrito igual que en el correo,
                            incluyendo el "ING-".
                        </p>
                    </section>
                ) : null}

                {ingreso ? <ResultadoIngreso ingreso={ingreso} /> : null}
            </main>

            <PiePagina nombre={marca.nombre} contacto={contacto} />
        </div>
    );
}

/** Pie de página con los datos de contacto del laboratorio. */
function PiePagina({
    nombre,
    contacto,
}: {
    nombre: string;
    contacto: ContactoEmpresa | null;
}) {
    return (
        <footer className="border-t bg-background" data-test="seguimiento-pie">
            <div className="grid gap-6 px-4 py-6 text-sm sm:grid-cols-2 md:px-8 lg:grid-cols-3">
                <div className="grid gap-1">
                    <p className="font-semibold">
                        {contacto?.nombre ?? nombre}
                    </p>
                    {contacto?.nit ? (
                        <p className="text-muted-foreground">
                            NIT {contacto.nit}
                        </p>
                    ) : null}
                    <p className="text-muted-foreground">
                        ¿Dudas sobre tus equipos? Comunícate con nosotros e
                        indícanos el código del ingreso.
                    </p>
                </div>
                {contacto ? (
                    <ul className="grid content-start gap-2 text-muted-foreground lg:col-span-2 lg:grid-cols-2">
                        <DatoContacto icono={MapPin}>
                            {contacto.direccion}
                        </DatoContacto>
                        <DatoContacto icono={Phone}>
                            <a
                                href={`tel:${contacto.telefono.replace(/\s+/g, '')}`}
                                className="hover:text-foreground hover:underline"
                            >
                                {contacto.telefono}
                            </a>
                        </DatoContacto>
                        {contacto.email ? (
                            <DatoContacto icono={Mail}>
                                <a
                                    href={`mailto:${contacto.email}`}
                                    className="break-all hover:text-foreground hover:underline"
                                >
                                    {contacto.email}
                                </a>
                            </DatoContacto>
                        ) : null}
                    </ul>
                ) : null}
            </div>
            <p className="border-t px-4 py-3 text-center text-xs text-muted-foreground md:px-8">
                © {new Date().getFullYear()} {contacto?.nombre ?? nombre}. Todos
                los derechos reservados.
            </p>
        </footer>
    );
}

function DatoContacto({
    icono: Icono,
    children,
}: {
    icono: LucideIcon;
    children: React.ReactNode;
}) {
    return (
        <li className="flex items-start gap-2">
            <Icono className="mt-0.5 size-4 shrink-0" />
            <span className="min-w-0">{children}</span>
        </li>
    );
}

/** La etapa en la que va el equipo: el último paso de su historial. */
function etapaActual(equipo: EquipoSeguimiento): string {
    if (equipo.ingresado === false) {
        return 'No ingresó';
    }

    return equipo.historial.at(-1)?.fase ?? 'Sin seguimiento';
}

/**
 * El ingreso y sus equipos en una tabla, que puede tener decenas de filas: con un buscador
 * y filtros por etapa (con cuántos equipos van en cada una) para encontrar uno rápido.
 */
function ResultadoIngreso({ ingreso }: { ingreso: IngresoSeguimiento }) {
    const [busqueda, setBusqueda] = useState('');
    const [etapa, setEtapa] = useState(TODAS);
    const [detalle, setDetalle] = useState<EquipoSeguimiento | null>(null);

    const etapas = Object.entries(
        ingreso.equipos.reduce<Record<string, number>>((conteo, equipo) => {
            const nombre = etapaActual(equipo);
            conteo[nombre] = (conteo[nombre] ?? 0) + 1;

            return conteo;
        }, {}),
    );

    const termino = busqueda.trim().toLowerCase();
    const visibles = ingreso.equipos.filter(
        (equipo) =>
            (etapa === TODAS || etapaActual(equipo) === etapa) &&
            (termino === '' ||
                [
                    equipo.codigo,
                    equipo.tipo_equipo,
                    equipo.modelo,
                    equipo.numero_serie,
                ].some((valor) => valor?.toLowerCase().includes(termino))),
    );

    return (
        <section className="grid gap-4" data-test="seguimiento-resultado">
            <div
                className="grid gap-4 rounded-xl border bg-background p-4 shadow-xs sm:p-5"
                data-test="seguimiento-ingreso"
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-xs text-muted-foreground">Ingreso</p>
                        <p className="font-mono text-lg font-semibold">
                            {ingreso.codigo}
                        </p>
                    </div>
                    <Badge
                        className={colorEstadoIngreso[ingreso.estado_ingreso]}
                    >
                        {estadosIngreso.find(
                            (opcion) => opcion.value === ingreso.estado_ingreso,
                        )?.label ?? ingreso.estado_ingreso}
                    </Badge>
                </div>
                <dl className="grid grid-cols-2 gap-3 border-t pt-4 text-sm sm:grid-cols-3">
                    <DatoIngreso
                        etiqueta="Cliente"
                        className="col-span-2 sm:col-span-1"
                    >
                        {ingreso.cliente ?? '—'}
                    </DatoIngreso>
                    <DatoIngreso etiqueta="Bahía">{ingreso.bahia}</DatoIngreso>
                    <DatoIngreso etiqueta="Fecha de ingreso">
                        {ingreso.fecha_ingreso
                            ? formatearFechaHora(ingreso.fecha_ingreso)
                            : formatearFecha(
                                  ingreso.fecha_recepcion,
                                  'Aún no se reciben los equipos',
                              )}
                    </DatoIngreso>
                </dl>
            </div>

            {ingreso.equipos.length === 0 ? (
                <p className="rounded-xl border bg-background p-6 text-sm text-muted-foreground">
                    Este ingreso no tiene equipos registrados.
                </p>
            ) : (
                <div className="grid gap-3 rounded-xl border bg-background p-4 shadow-xs sm:p-5">
                    <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
                        {[
                            [TODAS, ingreso.equipos.length] as const,
                            ...etapas,
                        ].map(([nombre, cantidad]) => (
                            <Button
                                key={nombre}
                                type="button"
                                size="sm"
                                variant={
                                    etapa === nombre ? 'default' : 'outline'
                                }
                                className="h-7 shrink-0 rounded-full px-3 text-xs"
                                onClick={() => setEtapa(nombre)}
                                data-test="seguimiento-etapa"
                            >
                                {nombre === TODAS ? 'Todos' : nombre}
                                <span className="tabular-nums opacity-70">
                                    {cantidad}
                                </span>
                            </Button>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="relative w-full sm:w-72">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={busqueda}
                                onChange={(evento) =>
                                    setBusqueda(evento.target.value)
                                }
                                placeholder="Buscar código, tipo, modelo o serie"
                                className="pl-8"
                                aria-label="Buscar equipo"
                                data-test="seguimiento-buscar-equipo"
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {visibles.length === ingreso.equipos.length
                                ? `${ingreso.equipos.length} equipos`
                                : `${visibles.length} de ${ingreso.equipos.length} equipos`}
                        </p>
                    </div>

                    <ul
                        className="grid gap-2 md:hidden"
                        data-test="seguimiento-tarjetas"
                    >
                        {visibles.map((equipo) => (
                            <TarjetaEquipo
                                key={equipo.codigo}
                                equipo={equipo}
                                onVerHistorial={() => setDetalle(equipo)}
                            />
                        ))}
                        {visibles.length === 0 ? (
                            <li className="rounded-lg border px-3 py-8 text-center text-sm text-muted-foreground">
                                Ningún equipo coincide con la búsqueda.
                            </li>
                        ) : null}
                    </ul>

                    <div className="hidden overflow-x-auto rounded-lg border md:block">
                        <table className="w-full min-w-[860px] text-sm">
                            <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2.5 font-medium">
                                        Equipo
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">
                                        Ingreso
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">
                                        Servicio
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">
                                        Estado actual
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">
                                        Laboratorio
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">
                                        Actualizado
                                    </th>
                                    <th className="px-3 py-2.5">
                                        <span className="sr-only">
                                            Historial
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visibles.map((equipo) => (
                                    <FilaEquipo
                                        key={equipo.codigo}
                                        equipo={equipo}
                                        onVerHistorial={() =>
                                            setDetalle(equipo)
                                        }
                                    />
                                ))}
                                {visibles.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-3 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            Ningún equipo coincide con la
                                            búsqueda.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <HistorialEquipo
                equipo={detalle}
                onClose={() => setDetalle(null)}
            />
        </section>
    );
}

function DatoIngreso({
    etiqueta,
    className,
    children,
}: {
    etiqueta: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={className}>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium">{children}</dd>
        </div>
    );
}

/** Etapa y estado en que va el equipo, con el color de la etapa. */
function EstadoEquipo({ equipo }: { equipo: EquipoSeguimiento }) {
    const actual = equipo.historial.at(-1);

    if (equipo.ingresado === false) {
        return (
            <Badge variant="outline" className="w-fit text-red-700">
                No ingresó
            </Badge>
        );
    }

    if (!actual) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                'w-fit max-w-full whitespace-normal',
                tonoDelEstado(actual.estado) === 'malo'
                    ? 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                    : (COLOR_ETAPA[actual.fase] ??
                          'border-transparent bg-muted text-foreground'),
            )}
        >
            {actual.fase}
            {actual.estado ? ` · ${actual.estado}` : ''}
        </Badge>
    );
}

/** El equipo en el celular: una tarjeta que al tocarla abre su traza. */
function TarjetaEquipo({
    equipo,
    onVerHistorial,
}: {
    equipo: EquipoSeguimiento;
    onVerHistorial: () => void;
}) {
    const actual = equipo.historial.at(-1);
    const conHistorial = equipo.historial.length > 0;

    return (
        <li data-test="seguimiento-equipo-tarjeta">
            <button
                type="button"
                onClick={onVerHistorial}
                disabled={!conHistorial}
                className="grid w-full gap-2 rounded-lg border bg-background p-3 text-left text-sm transition-colors active:bg-muted/60 disabled:cursor-default"
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="font-medium">
                            {equipo.codigo} · {equipo.tipo_equipo}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Modelo: {equipo.modelo || '—'} · Serie:{' '}
                            {equipo.numero_serie || '—'}
                        </p>
                    </div>
                    {conHistorial ? (
                        <span className="flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground">
                            Ver traza
                            <ChevronRight className="size-4" />
                        </span>
                    ) : null}
                </div>
                <EstadoEquipo equipo={equipo} />
                <dl className="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                    <DatoTarjeta etiqueta="Servicio">
                        {equipo.tipo_servicio}
                    </DatoTarjeta>
                    <DatoTarjeta etiqueta="Ingreso">
                        {formatearFechaHora(equipo.fecha_ingreso)}
                    </DatoTarjeta>
                    {equipo.ingresado !== false && actual?.laboratorio ? (
                        <DatoTarjeta etiqueta="Laboratorio">
                            {actual.laboratorio}
                        </DatoTarjeta>
                    ) : null}
                    <DatoTarjeta etiqueta="Actualizado">
                        {formatearFechaHora(actual?.fecha)}
                    </DatoTarjeta>
                </dl>
            </button>
        </li>
    );
}

function DatoTarjeta({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-muted-foreground">{etiqueta}</dt>
            <dd className="break-words">{children}</dd>
        </div>
    );
}

function FilaEquipo({
    equipo,
    onVerHistorial,
}: {
    equipo: EquipoSeguimiento;
    onVerHistorial: () => void;
}) {
    const actual = equipo.historial.at(-1);

    return (
        <tr className="border-t align-top" data-test="seguimiento-equipo">
            <td className="px-3 py-2.5">
                <span className="font-medium">{equipo.codigo}</span> ·{' '}
                {equipo.tipo_equipo}
                <span className="block text-xs text-muted-foreground">
                    Modelo: {equipo.modelo || '—'} · Serie:{' '}
                    {equipo.numero_serie || '—'}
                </span>
            </td>
            <td className="px-3 py-2.5 whitespace-nowrap">
                {equipo.fecha_ingreso ? (
                    formatearFechaHora(equipo.fecha_ingreso)
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-3 py-2.5 whitespace-nowrap">
                {equipo.tipo_servicio}
            </td>
            <td className="px-3 py-2.5">
                <EstadoEquipo equipo={equipo} />
            </td>
            <td className="px-3 py-2.5">
                {equipo.ingresado !== false && actual?.laboratorio ? (
                    actual.laboratorio
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-3 py-2.5 whitespace-nowrap text-muted-foreground">
                {formatearFechaHora(actual?.fecha)}
            </td>
            <td className="px-3 py-2.5 text-right">
                {equipo.historial.length > 0 ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onVerHistorial}
                        data-test="seguimiento-ver-historial"
                    >
                        <History /> Historial
                    </Button>
                ) : null}
            </td>
        </tr>
    );
}

/** Ícono de cada fase (por su nombre visible), como en la traza de los equipos. */
const ICONO_FASE: Record<string, LucideIcon> = {
    'En bahía': MapPin,
    Ingreso: PackageCheck,
    Mantenimiento: Wrench,
    Calibración: Gauge,
    Despacho: Truck,
};

/** Estados que cierran bien la fase (verde) o mal (rojo); los demás van en curso (ámbar). */
const ESTADOS_BUENOS = [
    'RECIBIDO',
    'FINALIZADO',
    'ENTREGADO',
    'AUTORIZADO',
    'APROBADO',
];
const ESTADOS_MALOS = [
    'CANCELADO',
    'FUERA_DE_SERVICIO',
    'RECHAZADO',
    'DEVOLVER_MANTENIMIENTO',
];

const CLASE_BADGE: Record<TonoPaso, string> = {
    bueno: '!border-transparent !bg-emerald-500 !text-white',
    malo: '!border-transparent !bg-red-500 !text-white',
    aviso: '!border-transparent !bg-amber-500 !text-white',
    neutro: '',
};

/** El estado llega legible ("Fuera de servicio"); se compara con su clave (FUERA_DE_SERVICIO). */
function tonoDelEstado(estado: string | null): TonoPaso {
    if (!estado) {
        return 'neutro';
    }

    const clave = estado.toUpperCase().replaceAll(' ', '_');

    if (ESTADOS_BUENOS.includes(clave)) {
        return 'bueno';
    }

    return ESTADOS_MALOS.includes(clave) ? 'malo' : 'aviso';
}

/**
 * Recorrido del equipo dibujado como en las apps de entregas: las etapas por las que pasó,
 * la actual resaltada y las que faltan bloqueadas, unidas por una barra que se va llenando.
 * Si la etapa actual terminó mal (fuera de servicio, cancelado…) se pinta de rojo.
 */
function ProgresoEquipo({ equipo }: { equipo: EquipoSeguimiento }) {
    const [lleno, setLleno] = useState(false);

    useEffect(() => {
        const cuadro = requestAnimationFrame(() => setLleno(true));

        return () => cancelAnimationFrame(cuadro);
    }, []);

    const alcanzadas = equipo.historial
        .map((paso) => paso.fase)
        .filter((fase, indice, fases) => fases.indexOf(fase) === indice);
    const etapas = [...alcanzadas, ...equipo.siguientes];
    const actual = alcanzadas.length - 1;
    const ultimo = equipo.historial.at(-1);
    const fallo =
        equipo.ingresado === false ||
        tonoDelEstado(ultimo?.estado ?? null) === 'malo';
    const terminado = !fallo && equipo.siguientes.length === 0;

    if (actual < 0) {
        return null;
    }

    const avance = etapas.length > 1 ? (actual / (etapas.length - 1)) * 100 : 0;
    const margen = `${50 / etapas.length}%`;

    return (
        <div
            className="rounded-lg border bg-muted/30 px-2 pt-4 pb-3"
            data-test="seguimiento-progreso"
        >
            <ol className="relative flex">
                <span
                    className="absolute top-[1.125rem] h-1 rounded-full bg-muted"
                    style={{ left: margen, right: margen }}
                />
                <span
                    className={cn(
                        'absolute top-[1.125rem] h-1 rounded-full transition-[width] duration-1000 ease-out',
                        fallo ? 'bg-red-500' : 'bg-emerald-500',
                    )}
                    style={{
                        left: margen,
                        width: lleno
                            ? `calc((100% - 2 * ${margen}) * ${avance / 100})`
                            : 0,
                    }}
                />
                {etapas.map((fase, indice) => {
                    const pasada =
                        indice < actual || (indice === actual && terminado);
                    const esActual = indice === actual && !terminado;
                    const Icono =
                        indice > actual ? Lock : (ICONO_FASE[fase] ?? Route);

                    return (
                        <li
                            key={fase}
                            className="relative flex flex-1 flex-col items-center gap-1.5 text-center"
                            data-test="seguimiento-progreso-etapa"
                        >
                            <span
                                className={cn(
                                    'relative flex size-10 items-center justify-center rounded-full border-2 transition-colors duration-500',
                                    pasada &&
                                        'border-emerald-500 bg-emerald-500 text-white',
                                    esActual &&
                                        (fallo
                                            ? 'border-red-500 bg-red-500 text-white'
                                            : 'border-emerald-500 bg-background text-emerald-600'),
                                    indice > actual &&
                                        'border-dashed border-muted-foreground/40 bg-background text-muted-foreground/60',
                                )}
                            >
                                {esActual ? (
                                    <span
                                        className={cn(
                                            'absolute inset-0 animate-ping rounded-full opacity-30',
                                            fallo
                                                ? 'bg-red-500'
                                                : 'bg-emerald-500',
                                        )}
                                    />
                                ) : null}
                                {esActual && fallo ? (
                                    <CircleX className="relative size-5" />
                                ) : pasada && indice < actual ? (
                                    <Check className="relative size-5" />
                                ) : (
                                    <Icono className="relative size-[1.125rem]" />
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-[11px] leading-tight sm:text-xs',
                                    indice > actual
                                        ? 'text-muted-foreground'
                                        : 'font-medium',
                                    esActual && fallo && 'text-red-600',
                                )}
                            >
                                {fase}
                            </span>
                        </li>
                    );
                })}
            </ol>
            <p
                className={cn(
                    'mt-3 text-center text-xs',
                    fallo
                        ? 'font-medium text-red-600'
                        : 'text-muted-foreground',
                )}
            >
                {fallo
                    ? equipo.ingresado === false
                        ? 'El equipo no ingresó al laboratorio.'
                        : `${ultimo?.fase} · ${ultimo?.estado}`
                    : terminado
                      ? 'El equipo completó su recorrido.'
                      : `Va en ${etapas[actual].toLowerCase()}${ultimo?.estado ? ` · ${ultimo.estado.toLowerCase()}` : ''}. Siguiente: ${etapas[actual + 1].toLowerCase()}.`}
            </p>
        </div>
    );
}

/** Traza del equipo: cada etapa por la que ha pasado, del más antiguo al más reciente. */
function HistorialEquipo({
    equipo,
    onClose,
}: {
    equipo: EquipoSeguimiento | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={equipo !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {equipo ? (
                <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Route className="size-5 text-muted-foreground" />
                            {equipo.codigo} · {equipo.tipo_equipo}
                        </DialogTitle>
                        <DialogDescription>
                            Modelo: {equipo.modelo || '—'} · Serie:{' '}
                            {equipo.numero_serie || '—'} ·{' '}
                            {equipo.tipo_servicio}
                        </DialogDescription>
                    </DialogHeader>
                    <ProgresoEquipo equipo={equipo} />
                    <LineaDeTiempo testId="seguimiento-historial">
                        {equipo.historial.map((paso, indice) => {
                            const tono = tonoDelEstado(paso.estado);
                            const esElActual =
                                indice === equipo.historial.length - 1;

                            return (
                                <Paso
                                    key={indice}
                                    icono={ICONO_FASE[paso.fase] ?? Route}
                                    tono={tono}
                                    titulo={
                                        <span className="flex flex-wrap items-center gap-2">
                                            {paso.fase}
                                            {paso.estado ? (
                                                <Badge
                                                    variant={
                                                        tono === 'neutro'
                                                            ? 'secondary'
                                                            : 'default'
                                                    }
                                                    className={
                                                        CLASE_BADGE[tono]
                                                    }
                                                >
                                                    {paso.estado}
                                                </Badge>
                                            ) : null}
                                            {esElActual ? (
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    (estado actual)
                                                </span>
                                            ) : null}
                                        </span>
                                    }
                                >
                                    <p className="text-xs">
                                        {formatearFechaHora(paso.fecha)}
                                    </p>
                                    {paso.laboratorio ? (
                                        <p className="text-foreground">
                                            Laboratorio: {paso.laboratorio}
                                        </p>
                                    ) : null}
                                </Paso>
                            );
                        })}
                        {equipo.siguientes.map((fase, indice) => (
                            <li
                                key={fase}
                                className="relative space-y-1 opacity-60"
                                data-test="seguimiento-siguiente"
                            >
                                <span className="absolute top-0 -left-[1.95rem] flex size-6 items-center justify-center rounded-full border border-dashed bg-muted text-muted-foreground">
                                    <Lock className="size-3" />
                                </span>
                                <div className="flex flex-wrap items-center gap-2 text-sm font-medium text-muted-foreground">
                                    {fase}
                                    {indice === 0 ? (
                                        <Badge
                                            variant="outline"
                                            className="border-dashed font-normal"
                                        >
                                            Siguiente paso
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Pendiente
                                </p>
                            </li>
                        ))}
                    </LineaDeTiempo>
                </DialogContent>
            ) : null}
        </Dialog>
    );
}
