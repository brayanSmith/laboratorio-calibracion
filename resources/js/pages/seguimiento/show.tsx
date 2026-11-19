import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CalendarClock,
    Clock,
    ChevronLeft,
    ChevronRight,
    Camera,
    Download,
    ExternalLink,
    FileText,
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
import { useEffect, useMemo, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import type { DataTableColumn } from '@/components/data-table';
import { CeldaEquipo, textoEquipo } from '@/components/equipos/celdas-equipo';
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
    /** Fotos de cómo llegó el equipo, tomadas al recibirlo. */
    fotos: string[];
    /** PDF de los servicios terminados: informes de mantenimiento y certificados. */
    documentos: DocumentoEquipo[];
};

type DocumentoEquipo = {
    tipo: 'MANTENIMIENTO' | 'CALIBRACION';
    nombre: string;
    fecha: string | null;
    url: string;
    /** Laboratorio externo que lo emitió; null si es del laboratorio propio. */
    laboratorio: string | null;
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
            {/* El ícono de la pestaña con el logo de la empresa del ingreso consultado (o la
                marca de la plataforma); esta página no usa layout, que es donde va. */}
            <Head title="Seguimiento de equipos">
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

    const columnas = useMemo(() => columnasSeguimiento(setDetalle), []);
    const porEtapa = ingreso.equipos.filter(
        (equipo) => etapa === TODAS || etapaActual(equipo) === etapa,
    );
    const termino = busqueda.trim().toLowerCase();
    const visibles = porEtapa.filter(
        (equipo) =>
            termino === '' ||
            [
                equipo.codigo,
                equipo.tipo_equipo,
                equipo.modelo,
                equipo.numero_serie,
            ].some((valor) => valor?.toLowerCase().includes(termino)),
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

                    <div className="flex flex-wrap items-center justify-between gap-2 md:hidden">
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

                    {/* En pantallas grandes, la tabla de la app: se ordena tocando el
                        encabezado y se filtra por columna. */}
                    <div className="hidden md:block">
                        <DataTable
                            data={porEtapa}
                            columns={columnas}
                            searchPlaceholder="Buscar código, tipo, modelo o serie"
                            emptyMessage="Ningún equipo en esta etapa."
                            pageSize={25}
                            rowTestId="seguimiento-equipo"
                        />
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
        <li
            className="overflow-hidden rounded-lg border bg-background"
            data-test="seguimiento-equipo-tarjeta"
        >
            <button
                type="button"
                onClick={onVerHistorial}
                disabled={!conHistorial}
                className="grid w-full gap-2 p-3 text-left text-sm transition-colors active:bg-muted/60 disabled:cursor-default"
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
            {equipo.fotos.length > 0 ? (
                <div className="flex items-center justify-between gap-2 border-t bg-muted/30 px-3 py-2">
                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Camera className="size-3.5" />
                        Fotos al recibirlo
                    </span>
                    <MiniaturasFotos equipo={equipo} maximo={4} />
                </div>
            ) : null}
            {equipo.documentos.length > 0 ? (
                <div className="grid gap-1.5 border-t bg-muted/30 px-3 py-2">
                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                        <FileText className="size-3.5" />
                        Certificados e informes
                    </span>
                    <DocumentosEquipo equipo={equipo} />
                </div>
            ) : null}
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

/** Laboratorio donde va el servicio del equipo; vacío si no aplica. */
function laboratorioActual(equipo: EquipoSeguimiento): string {
    return equipo.ingresado === false
        ? ''
        : (equipo.historial.at(-1)?.laboratorio ?? '');
}

/** Texto de la etapa y el estado actuales, para ordenar y filtrar por ellos. */
function estadoActualTexto(equipo: EquipoSeguimiento): string {
    const actual = equipo.historial.at(-1);

    return equipo.ingresado === false || !actual
        ? etapaActual(equipo)
        : [actual.fase, actual.estado].filter(Boolean).join(' · ');
}

const Vacio = () => <span className="text-muted-foreground">—</span>;

const columnHelper = createDataTableColumnHelper<EquipoSeguimiento>();

/**
 * Columnas de la tabla de equipos (la DataTable de la app): cada una se ordena tocando su
 * encabezado y se filtra con su campo. Las fechas se ordenan por su valor ISO.
 */
function columnasSeguimiento(
    onVerHistorial: (equipo: EquipoSeguimiento) => void,
): DataTableColumn<EquipoSeguimiento>[] {
    return [
        columnHelper.accessor(
            (equipo) =>
                textoEquipo(
                    equipo.codigo,
                    equipo.tipo_equipo,
                    equipo.modelo,
                    equipo.numero_serie,
                ),
            {
                id: 'equipo',
                header: 'Equipo',
                cell: ({ row }) => (
                    <CeldaEquipo
                        codigo={
                            <span className="font-medium">
                                {row.original.codigo}
                            </span>
                        }
                        tipo={row.original.tipo_equipo}
                        modelo={row.original.modelo}
                        serie={row.original.numero_serie}
                    />
                ),
            },
        ),
        columnHelper.accessor((equipo) => equipo.fecha_ingreso ?? '', {
            id: 'ingreso',
            header: 'Ingreso',
            meta: { icono: CalendarClock },
            cell: ({ row }) =>
                row.original.fecha_ingreso ? (
                    <span className="whitespace-nowrap">
                        {formatearFechaHora(row.original.fecha_ingreso)}
                    </span>
                ) : (
                    <Vacio />
                ),
        }),
        columnHelper.accessor(laboratorioActual, {
            id: 'laboratorio',
            header: 'Laboratorio',
            cell: (info) => info.getValue() || <Vacio />,
        }),
        columnHelper.accessor('tipo_servicio', { header: 'Servicio' }),
        columnHelper.accessor(estadoActualTexto, {
            id: 'estado',
            header: 'Estado actual',
            meta: { icono: Activity },
            cell: ({ row }) => <EstadoEquipo equipo={row.original} />,
        }),
        columnHelper.accessor((equipo) => equipo.fotos.length, {
            id: 'fotos',
            header: 'Fotos',
            meta: { icono: Camera },
            enableColumnFilter: false,
            cell: ({ row }) => <MiniaturasFotos equipo={row.original} />,
        }),
        columnHelper.accessor(
            (equipo) =>
                equipo.documentos
                    .map((documento) =>
                        [documento.nombre, documento.laboratorio]
                            .filter(Boolean)
                            .join(' '),
                    )
                    .join(' · '),
            {
                id: 'documentos',
                header: 'Documentos',
                meta: { icono: FileText },
                cell: ({ row }) => <DocumentosEquipo equipo={row.original} />,
            },
        ),
        columnHelper.accessor(
            (equipo) => equipo.historial.at(-1)?.fecha ?? '',
            {
                id: 'actualizado',
                header: 'Actualizado',
                meta: { icono: Clock },
                cell: (info) =>
                    info.getValue() ? (
                        <span className="whitespace-nowrap text-muted-foreground">
                            {formatearFechaHora(info.getValue())}
                        </span>
                    ) : (
                        <Vacio />
                    ),
            },
        ),
        columnHelper.display({
            id: 'historial',
            header: '',
            enableSorting: false,
            enableColumnFilter: false,
            cell: ({ row }) =>
                row.original.historial.length > 0 ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onVerHistorial(row.original)}
                        data-test="seguimiento-ver-historial"
                    >
                        <History /> Historial
                    </Button>
                ) : null,
        }),
    ];
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
 * Hasta dónde llena la barra el tramo hacia la siguiente etapa cuando la actual termina: a
 * mitad, para que no parezca que la siguiente ya empezó.
 */
const TRAMO_POR_ETAPA = 0.5;

/** Los pasos de una etapa y en cuál va el equipo (índice en `pasos`). */
type Subpasos = { pasos: string[]; alcanzado: number };

/**
 * Pasos de cada etapa, en orden, y en qué paso queda cada estado (por su clave, ej.
 * FALTA_REPUESTOS). Un estado que no aparece se toma como el primer paso.
 */
const PASOS_ETAPA: Record<
    string,
    { pasos: string[]; estados: Record<string, number> }
> = {
    Ingreso: {
        pasos: ['Agendado', 'Recibido'],
        estados: { RECIBIDO: 1, CANCELADO: 1, RE_AGENDADO: 1 },
    },
    Mantenimiento: {
        pasos: ['Agendado', 'En proceso', 'Finalizado'],
        estados: {
            EN_PROCESO: 1,
            FALTA_REPUESTOS: 1,
            FINALIZADO: 2,
            FUERA_DE_SERVICIO: 2,
            CANCELADO: 2,
        },
    },
    Calibración: {
        pasos: ['Agendada', 'En proceso', 'Finalizada'],
        estados: {
            EN_PROCESO: 1,
            FINALIZADO: 2,
            DEVOLVER_MANTENIMIENTO: 2,
            FUERA_DE_SERVICIO: 2,
        },
    },
    Despacho: {
        pasos: ['Autorizado', 'Entregado'],
        estados: { ENTREGADO: 1 },
    },
};

/** En qué paso de su etapa va el equipo, según el estado de su último seguimiento. */
function subpasosDe(etapa: string, estado: string | null): Subpasos {
    const definicion = PASOS_ETAPA[etapa];

    if (!definicion) {
        return { pasos: [etapa], alcanzado: 0 };
    }

    const clave = (estado ?? '').toUpperCase().replaceAll(' ', '_');

    return {
        pasos: definicion.pasos,
        alcanzado: definicion.estados[clave] ?? 0,
    };
}

/** Avance de la etapa (0 a 1): el primer paso es 0 y el último, 1. */
function avanceDeSubpasos({ pasos, alcanzado }: Subpasos): number {
    return pasos.length > 1 ? alcanzado / (pasos.length - 1) : 1;
}

/** Anillo alrededor del círculo de la etapa actual, que se llena con su avance. */
function AnilloAvance({ porcentaje }: { porcentaje: number }) {
    return (
        <svg
            viewBox="0 0 44 44"
            className="pointer-events-none absolute -inset-[5px] size-[calc(100%+10px)] -rotate-90"
            aria-hidden="true"
        >
            <circle
                cx="22"
                cy="22"
                r="20"
                fill="none"
                strokeWidth="3"
                className="stroke-emerald-500/15"
            />
            <circle
                cx="22"
                cy="22"
                r="20"
                fill="none"
                strokeWidth="3"
                strokeLinecap="round"
                pathLength={100}
                strokeDasharray={`${Math.max(porcentaje, 4)} 100`}
                className="stroke-emerald-500 transition-[stroke-dasharray] duration-1000 ease-out"
            />
        </svg>
    );
}

/** Los pasos de la etapa actual, con los ya hechos marcados y el actual resaltado. */
function PasosDeEtapa({
    etapa,
    subpasos,
    fallo,
}: {
    etapa: string;
    subpasos: Subpasos;
    fallo: boolean;
}) {
    return (
        <div
            className="mt-3 rounded-md border bg-background px-3 py-2"
            data-test="seguimiento-pasos-etapa"
        >
            <p className="mb-1.5 text-[11px] font-medium text-muted-foreground">
                Pasos de {etapa.toLowerCase()}
            </p>
            <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs">
                {subpasos.pasos.map((paso, indice) => {
                    const hecho = indice < subpasos.alcanzado;
                    const enCurso = indice === subpasos.alcanzado;

                    return (
                        <li key={paso} className="flex items-center gap-1.5">
                            {indice > 0 ? (
                                <span
                                    className={cn(
                                        'h-px w-4',
                                        hecho || enCurso
                                            ? 'bg-emerald-500'
                                            : 'bg-border',
                                    )}
                                />
                            ) : null}
                            <span
                                className={cn(
                                    'flex items-center gap-1 rounded-full px-2 py-0.5',
                                    hecho &&
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                    enCurso &&
                                        (fallo
                                            ? 'bg-red-100 font-medium text-red-700 dark:bg-red-950 dark:text-red-300'
                                            : 'bg-emerald-500 font-medium text-white'),
                                    !hecho &&
                                        !enCurso &&
                                        'border border-dashed text-muted-foreground',
                                )}
                            >
                                {hecho ? <Check className="size-3" /> : null}
                                {enCurso && fallo ? (
                                    <CircleX className="size-3" />
                                ) : null}
                                {paso}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
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

    if (actual < 0) {
        return null;
    }

    // Cuánto lleva la etapa actual de sus propios pasos (ej. mantenimiento en proceso: 1 de
    // 2 tramos), para no darla por terminada antes de tiempo.
    const subpasos = subpasosDe(etapas[actual], ultimo?.estado ?? null);
    const etapaCompleta =
        !fallo && subpasos.alcanzado === subpasos.pasos.length - 1;
    const terminado = etapaCompleta && equipo.siguientes.length === 0;
    const avanceEtapa = fallo ? 0 : avanceDeSubpasos(subpasos);
    const avance =
        etapas.length > 1
            ? (Math.min(
                  actual +
                      (actual < etapas.length - 1
                          ? avanceEtapa * TRAMO_POR_ETAPA
                          : 0),
                  etapas.length - 1,
              ) /
                  (etapas.length - 1)) *
              100
            : 0;
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
                        indice < actual || (indice === actual && etapaCompleta);
                    const esActual = indice === actual && !etapaCompleta;
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
                                {esActual && !fallo ? (
                                    <AnilloAvance
                                        porcentaje={
                                            lleno ? avanceEtapa * 100 : 0
                                        }
                                    />
                                ) : null}
                                {esActual && fallo ? (
                                    <CircleX className="relative size-5" />
                                ) : pasada ? (
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
                                {esActual &&
                                !fallo &&
                                subpasos.pasos.length > 1 ? (
                                    <span className="block font-normal text-muted-foreground">
                                        {subpasos.alcanzado + 1} de{' '}
                                        {subpasos.pasos.length}
                                    </span>
                                ) : null}
                            </span>
                        </li>
                    );
                })}
            </ol>
            {!terminado && subpasos.pasos.length > 1 ? (
                <PasosDeEtapa
                    etapa={etapas[actual]}
                    subpasos={subpasos}
                    fallo={fallo}
                />
            ) : null}
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
                      : etapaCompleta
                        ? `${etapas[actual]} completado. Siguiente: ${etapas[actual + 1]?.toLowerCase() ?? '—'}.`
                        : `Va en ${etapas[actual].toLowerCase()}${ultimo?.estado ? ` · ${ultimo.estado.toLowerCase()}` : ''}.${etapas[actual + 1] ? ` Luego: ${etapas[actual + 1].toLowerCase()}.` : ''}`}
            </p>
        </div>
    );
}

/**
 * Miniaturas de las fotos de recepción en la tabla o la tarjeta del equipo: las primeras
 * `maximo` y un recuadro "+N" con las demás. Al tocar una se abre el visor en esa foto.
 */
function MiniaturasFotos({
    equipo,
    maximo = 3,
}: {
    equipo: EquipoSeguimiento;
    maximo?: number;
}) {
    const [abierta, setAbierta] = useState<number | null>(null);

    if (equipo.fotos.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const visibles = equipo.fotos.slice(0, maximo);
    const restantes = equipo.fotos.length - visibles.length;

    return (
        <>
            <ul
                className="flex shrink-0 items-center gap-1.5"
                aria-label={`Fotos de recepción de ${equipo.codigo}`}
                data-test="seguimiento-miniaturas"
            >
                {visibles.map((url, indice) => (
                    <li key={url} className="shrink-0">
                        <button
                            type="button"
                            onClick={() => setAbierta(indice)}
                            className="relative block overflow-hidden rounded-md border transition hover:ring-2 hover:ring-primary/40 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            aria-label={`Ver foto ${indice + 1} de ${equipo.codigo}`}
                        >
                            <img
                                src={url}
                                alt=""
                                className="size-11 max-w-none object-cover"
                                loading="lazy"
                            />
                            {indice === visibles.length - 1 && restantes > 0 ? (
                                <span className="absolute inset-0 flex items-center justify-center bg-black/55 text-xs font-semibold text-white">
                                    +{restantes}
                                </span>
                            ) : null}
                        </button>
                    </li>
                ))}
            </ul>
            <VisorFotos
                equipo={equipo}
                abierta={abierta}
                onCambiar={setAbierta}
            />
        </>
    );
}

/**
 * Botones con los PDF de los servicios terminados del equipo (informe de mantenimiento,
 * certificado de calibración). Al tocar uno se abre su vista previa.
 */
function DocumentosEquipo({ equipo }: { equipo: EquipoSeguimiento }) {
    const [abierto, setAbierto] = useState<DocumentoEquipo | null>(null);

    if (equipo.documentos.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <>
            <ul
                className="flex flex-wrap gap-1.5"
                data-test="seguimiento-documentos"
            >
                {equipo.documentos.map((documento) => (
                    <li key={documento.url}>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className={cn(
                                'h-7 gap-1 px-2 text-xs',
                                documento.tipo === 'CALIBRACION'
                                    ? 'border-violet-200 text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-300 dark:hover:bg-violet-950'
                                    : 'border-amber-200 text-amber-800 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950',
                            )}
                            onClick={() => setAbierto(documento)}
                            title={`${documento.nombre}${documento.laboratorio ? ` · ${documento.laboratorio}` : ''}${documento.fecha ? ` · ${formatearFecha(documento.fecha)}` : ''}`}
                            data-test="seguimiento-ver-documento"
                        >
                            <FileText className="size-3.5" />
                            {documento.tipo === 'CALIBRACION'
                                ? 'Certificado'
                                : 'Informe mant.'}
                            {documento.laboratorio ? (
                                <span className="rounded bg-current/10 px-1 text-[10px] font-normal">
                                    Externo
                                </span>
                            ) : null}
                        </Button>
                    </li>
                ))}
            </ul>
            <VisorDocumento
                equipo={equipo}
                documento={abierto}
                onClose={() => setAbierto(null)}
            />
        </>
    );
}

/**
 * Vista previa del PDF con el visor del navegador, sin librerías. En los celulares que no
 * muestran PDF dentro de la página (la mayoría de Android) se ve el aviso con los botones
 * para abrirlo o descargarlo.
 */
function VisorDocumento({
    equipo,
    documento,
    onClose,
}: {
    equipo: EquipoSeguimiento;
    documento: DocumentoEquipo | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={documento !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {documento ? (
                <DialogContent
                    className="flex h-[92dvh] max-h-[92dvh] flex-col gap-3 p-3 sm:max-w-4xl"
                    data-test="seguimiento-visor-documento"
                >
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <FileText className="size-4 text-muted-foreground" />
                            {documento.nombre}
                        </DialogTitle>
                        <DialogDescription>
                            {equipo.codigo} · {equipo.tipo_equipo}
                            {documento.fecha
                                ? ` · ${formatearFecha(documento.fecha)}`
                                : ''}
                            {documento.laboratorio
                                ? ` · Emitido por ${documento.laboratorio}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    <object
                        data={documento.url}
                        type="application/pdf"
                        className="min-h-0 w-full flex-1 rounded-md border bg-muted"
                        aria-label={documento.nombre}
                    >
                        <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                            <FileText className="size-10 text-muted-foreground" />
                            <p className="max-w-xs text-sm text-muted-foreground">
                                Tu navegador no puede mostrar el PDF aquí.
                                Ábrelo o descárgalo para verlo.
                            </p>
                            <Button asChild size="sm">
                                <a
                                    href={documento.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> Abrir PDF
                                </a>
                            </Button>
                        </div>
                    </object>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={documento.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink /> Abrir en otra pestaña
                            </a>
                        </Button>
                        <Button asChild size="sm">
                            <a href={documento.url} download>
                                <Download /> Descargar
                            </a>
                        </Button>
                    </div>
                </DialogContent>
            ) : null}
        </Dialog>
    );
}

/** Flecha sobre la foto grande para pasar a la anterior o la siguiente. */
function FlechaVisor({
    lado,
    disabled,
    onClick,
}: {
    lado: 'izquierda' | 'derecha';
    disabled: boolean;
    onClick: () => void;
}) {
    if (disabled) {
        return null;
    }

    const Icono = lado === 'izquierda' ? ChevronLeft : ChevronRight;

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'absolute top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white shadow transition hover:bg-black/70',
                lado === 'izquierda' ? 'left-2' : 'right-2',
            )}
            aria-label={
                lado === 'izquierda' ? 'Foto anterior' : 'Foto siguiente'
            }
        >
            <Icono className="size-5" />
        </button>
    );
}

/**
 * Una foto de recepción en grande, con flechas encima y debajo las miniaturas de todas para
 * saltar a cualquiera (también se pasa con las flechas del teclado).
 */
function VisorFotos({
    equipo,
    abierta,
    onCambiar,
}: {
    equipo: EquipoSeguimiento;
    abierta: number | null;
    onCambiar: (indice: number | null) => void;
}) {
    const total = equipo.fotos.length;

    return (
        <Dialog
            open={abierta !== null}
            onOpenChange={(open) => !open && onCambiar(null)}
        >
            {abierta !== null ? (
                <DialogContent
                    className="max-h-[95dvh] gap-3 p-3 sm:max-w-3xl"
                    onKeyDown={(evento) => {
                        if (evento.key === 'ArrowLeft' && abierta > 0) {
                            onCambiar(abierta - 1);
                        }

                        if (
                            evento.key === 'ArrowRight' &&
                            abierta < total - 1
                        ) {
                            onCambiar(abierta + 1);
                        }
                    }}
                    data-test="seguimiento-visor-foto"
                >
                    <DialogHeader>
                        <DialogTitle className="text-base">
                            {equipo.codigo} · Foto {abierta + 1} de {total}
                        </DialogTitle>
                        <DialogDescription>
                            Así llegó el equipo al laboratorio.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="relative">
                        <a
                            href={equipo.fotos[abierta]}
                            target="_blank"
                            rel="noreferrer"
                            title="Abrir en tamaño original"
                        >
                            <img
                                src={equipo.fotos[abierta]}
                                alt={`Foto ${abierta + 1} de ${equipo.codigo} al recibirlo`}
                                className="h-[min(60dvh,32rem)] w-full rounded-md bg-muted object-contain"
                            />
                        </a>
                        {total > 1 ? (
                            <>
                                <FlechaVisor
                                    lado="izquierda"
                                    disabled={abierta === 0}
                                    onClick={() => onCambiar(abierta - 1)}
                                />
                                <FlechaVisor
                                    lado="derecha"
                                    disabled={abierta === total - 1}
                                    onClick={() => onCambiar(abierta + 1)}
                                />
                            </>
                        ) : null}
                    </div>
                    {total > 1 ? (
                        <ul
                            className="-mx-1 flex snap-x gap-2 overflow-x-auto px-1 py-1"
                            data-test="seguimiento-visor-miniaturas"
                        >
                            {equipo.fotos.map((url, indice) => (
                                <li
                                    key={url}
                                    className="shrink-0 snap-start"
                                    ref={(elemento) => {
                                        if (indice === abierta) {
                                            elemento?.scrollIntoView({
                                                block: 'nearest',
                                                inline: 'nearest',
                                            });
                                        }
                                    }}
                                >
                                    <button
                                        type="button"
                                        onClick={() => onCambiar(indice)}
                                        className={cn(
                                            'block overflow-hidden rounded-md border-2 transition',
                                            indice === abierta
                                                ? 'border-primary'
                                                : 'border-transparent opacity-60 hover:opacity-100',
                                        )}
                                        aria-label={`Ver foto ${indice + 1}`}
                                        aria-current={indice === abierta}
                                    >
                                        <img
                                            src={url}
                                            alt=""
                                            className="size-14 max-w-none object-cover sm:size-16"
                                            loading="lazy"
                                        />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </DialogContent>
            ) : null}
        </Dialog>
    );
}

/**
 * Fotos de cómo llegó el equipo, como evidencia de su estado al recibirlo. Al tocar una
 * se ve en grande; en el celular se desliza de lado para ver las demás.
 */
function FotosEquipo({ equipo }: { equipo: EquipoSeguimiento }) {
    const [abierta, setAbierta] = useState<number | null>(null);

    if (equipo.fotos.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2" data-test="seguimiento-fotos">
            <p className="flex items-center gap-1.5 text-sm font-medium">
                <Camera className="size-4 text-muted-foreground" />
                Así llegó el equipo
                <span className="font-normal text-muted-foreground">
                    ({equipo.fotos.length})
                </span>
            </p>
            <ul className="-mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-1">
                {equipo.fotos.map((url, indice) => (
                    <li key={url} className="shrink-0 snap-start">
                        <button
                            type="button"
                            onClick={() => setAbierta(indice)}
                            className="block overflow-hidden rounded-lg border transition-opacity hover:opacity-80"
                        >
                            <img
                                src={url}
                                alt={`Foto ${indice + 1} de ${equipo.codigo} al recibirlo`}
                                className="size-20 object-cover sm:size-24"
                                loading="lazy"
                            />
                        </button>
                    </li>
                ))}
            </ul>

            <VisorFotos
                equipo={equipo}
                abierta={abierta}
                onCambiar={setAbierta}
            />
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
                    <FotosEquipo equipo={equipo} />
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
