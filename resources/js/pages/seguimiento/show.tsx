import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, PackageSearch, Search } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { colorEstadoIngreso, estadosIngreso } from '@/lib/estados-ingreso';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import { login, seguimiento } from '@/routes';
import type { EstadoIngreso } from '@/types';

type PasoSeguimiento = {
    fase: string;
    estado: string | null;
    fecha: string | null;
};

type EquipoSeguimiento = {
    codigo: string;
    tipo_equipo: string;
    modelo: string | null;
    numero_serie: string | null;
    tipo_servicio: string;
    /** null mientras el ingreso no se ha recibido. */
    ingresado: boolean | null;
    historial: PasoSeguimiento[];
};

type IngresoSeguimiento = {
    codigo: string;
    empresa: { nombre: string; logo_url: string | null } | null;
    bahia: string;
    estado_ingreso: EstadoIngreso;
    fecha_recepcion: string | null;
    equipos: EquipoSeguimiento[];
};

type Props = {
    codigo: string;
    ingreso: IngresoSeguimiento | null;
};

/**
 * Consulta pública del seguimiento de un ingreso: con el código que le llegó por correo,
 * el cliente ve en qué va cada equipo sin iniciar sesión.
 */
export default function SeguimientoShow({ codigo, ingreso }: Props) {
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
        <div className="min-h-svh bg-muted/40">
            <Head title="Seguimiento de equipos" />

            <header className="border-b bg-background">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-4">
                    <div className="flex items-center gap-3">
                        {marca.logo_url ? (
                            <img
                                src={marca.logo_url}
                                alt={`Logo de ${marca.nombre}`}
                                className="h-10 max-w-40 rounded-md bg-white object-contain"
                            />
                        ) : (
                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                        )}
                        <span className="font-semibold">{marca.nombre}</span>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={login()}>
                            <ArrowLeft /> Iniciar sesión
                        </Link>
                    </Button>
                </div>
            </header>

            <main className="mx-auto grid max-w-4xl gap-6 px-4 py-8">
                <section className="rounded-xl border bg-background p-6 shadow-xs">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Seguimiento de equipos
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ingresa el código del ingreso que te llegó por correo
                        para ver el estado de tus equipos.
                    </p>

                    <form
                        onSubmit={buscar}
                        className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"
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
                            data-test="seguimiento-buscar"
                        >
                            {buscando ? null : <Search />}
                            Consultar
                        </Button>
                    </form>
                </section>

                {codigo && !ingreso ? (
                    <section
                        className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-background p-10 text-center"
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
        </div>
    );
}

function ResultadoIngreso({ ingreso }: { ingreso: IngresoSeguimiento }) {
    return (
        <section className="grid gap-4" data-test="seguimiento-resultado">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-background p-5 shadow-xs">
                <div>
                    <p className="text-xs text-muted-foreground">Ingreso</p>
                    <p className="font-mono text-lg font-semibold">
                        {ingreso.codigo}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Bahía {ingreso.bahia}
                        {ingreso.fecha_recepcion
                            ? ` · Recibido el ${formatearFecha(ingreso.fecha_recepcion)}`
                            : ''}
                    </p>
                </div>
                <Badge className={colorEstadoIngreso[ingreso.estado_ingreso]}>
                    {estadosIngreso.find(
                        (opcion) => opcion.value === ingreso.estado_ingreso,
                    )?.label ?? ingreso.estado_ingreso}
                </Badge>
            </div>

            <p className="text-sm font-medium">
                Equipos ({ingreso.equipos.length})
            </p>

            {ingreso.equipos.length === 0 ? (
                <p className="rounded-xl border bg-background p-6 text-sm text-muted-foreground">
                    Este ingreso no tiene equipos registrados.
                </p>
            ) : (
                <ul className="grid gap-3">
                    {ingreso.equipos.map((equipo) => (
                        <EquipoTarjeta key={equipo.codigo} equipo={equipo} />
                    ))}
                </ul>
            )}
        </section>
    );
}

function EquipoTarjeta({ equipo }: { equipo: EquipoSeguimiento }) {
    const actual = equipo.historial.at(-1);

    return (
        <li
            className="rounded-xl border bg-background p-5 shadow-xs"
            data-test="seguimiento-equipo"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">
                        {equipo.codigo} · {equipo.tipo_equipo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Modelo: {equipo.modelo || '—'} · Serie:{' '}
                        {equipo.numero_serie || '—'} · {equipo.tipo_servicio}
                    </p>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {equipo.ingresado === false ? (
                        <Badge variant="outline" className="text-red-700">
                            No ingresó
                        </Badge>
                    ) : null}
                    {actual ? (
                        <Badge>
                            {actual.fase}
                            {actual.estado ? ` · ${actual.estado}` : ''}
                        </Badge>
                    ) : null}
                </div>
            </div>

            {equipo.historial.length > 0 ? (
                <ol className="mt-4 grid gap-2 border-l pl-4">
                    {equipo.historial.map((paso, indice) => (
                        <li key={indice} className="relative text-sm">
                            <span className="absolute top-1.5 -left-[21px] size-2.5 rounded-full border-2 border-background bg-primary" />
                            <span className="font-medium">{paso.fase}</span>
                            {paso.estado ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {paso.estado}
                                </span>
                            ) : null}
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(paso.fecha)}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : null}
        </li>
    );
}
