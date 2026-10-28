import { router } from '@inertiajs/react';
import { ArrowLeftRight, GripVertical, Plus, Search, X } from 'lucide-react';
import type { DragEvent, MouseEvent, PropsWithChildren } from 'react';
import { useMemo, useState } from 'react';
import BahiaController from '@/actions/App/Modules/Catalogos/Http/Controllers/BahiaController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Bahia, BahiaUsuarioOption } from '@/types';

type Props = PropsWithChildren<{
    bahias: Bahia[];
    usuarios: BahiaUsuarioOption[];
}>;

/** Ids de usuarios asignados, por id de bahía. */
type Asignaciones = Map<number, Set<number>>;

/**
 * Por bahía: las bahías a la izquierda reciben los usuarios que se arrastran desde la
 * derecha. Por usuario: al revés.
 */
type Modo = 'porBahia' | 'porUsuario';

type Item = { id: number; titulo: string; detalle: string };

/** Tipo de dato propio del arrastre, para no aceptar texto u otros arrastres. */
const TIPO_ARRASTRE = 'application/x-asignacion-bahias';

const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

const coincide = (item: Item, busqueda: string) =>
    normalizar(`${item.titulo} ${item.detalle}`).includes(
        normalizar(busqueda.trim()),
    );

function asignacionesIniciales(bahias: Bahia[]): Asignaciones {
    return new Map(
        bahias.map((bahia) => [bahia.id, new Set(bahia.usuario_ids)]),
    );
}

function Buscador({
    value,
    onChange,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
}) {
    return (
        <div className="relative">
            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="pl-8"
            />
        </div>
    );
}

/**
 * Asigna usuarios a las bahías arrastrándolos: se eligen uno o varios elementos de la
 * derecha (Shift+clic para un rango, Ctrl/⌘+clic para sumar o quitar uno) y se sueltan
 * sobre un elemento de la izquierda. "Invertir" cambia los lados, para asignar bahías a
 * un usuario. Sin mouse, el botón "Asignar" de cada destino hace lo mismo. Los cambios se
 * guardan juntos al final.
 */
export default function AsignarUsuariosBahiasModal({
    bahias,
    usuarios,
    children,
}: Props) {
    const [open, setOpen] = useState(false);
    const [asignaciones, setAsignaciones] = useState<Asignaciones>(() =>
        asignacionesIniciales(bahias),
    );
    const [modo, setModo] = useState<Modo>('porBahia');
    const [seleccion, setSeleccion] = useState<Set<number>>(new Set());
    const [ultimoElegido, setUltimoElegido] = useState<number | null>(null);
    const [destinoResaltado, setDestinoResaltado] = useState<number | null>(
        null,
    );
    const [busquedaDestinos, setBusquedaDestinos] = useState('');
    const [busquedaOrigenes, setBusquedaOrigenes] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});

    const itemsBahias: Item[] = useMemo(
        () =>
            bahias.map((bahia) => ({
                id: bahia.id,
                titulo: bahia.nombre,
                detalle: bahia.area_nombre,
            })),
        [bahias],
    );
    const itemsUsuarios: Item[] = useMemo(
        () =>
            usuarios.map((usuario) => ({
                id: usuario.id,
                titulo: usuario.name,
                detalle: usuario.email,
            })),
        [usuarios],
    );

    const porBahia = modo === 'porBahia';
    const destinos = porBahia ? itemsBahias : itemsUsuarios;
    const origenes = porBahia ? itemsUsuarios : itemsBahias;
    const nombreDestinos = porBahia ? 'Bahías' : 'Usuarios';
    const nombreOrigenes = porBahia ? 'usuarios' : 'bahías';

    const destinosFiltrados = destinos.filter((item) =>
        coincide(item, busquedaDestinos),
    );
    const origenesFiltrados = origenes.filter((item) =>
        coincide(item, busquedaOrigenes),
    );

    const estaAsignado = (destinoId: number, origenId: number) =>
        porBahia
            ? (asignaciones.get(destinoId)?.has(origenId) ?? false)
            : (asignaciones.get(origenId)?.has(destinoId) ?? false);

    const asignadosDe = (destinoId: number): Item[] =>
        origenes.filter((origen) => estaAsignado(destinoId, origen.id));

    /** En cuántos destinos está cada origen, para mostrarlo al lado de su nombre. */
    const cantidadDestinos = (origenId: number) =>
        destinos.filter((destino) => estaAsignado(destino.id, origenId)).length;

    const cambiar = (
        destinoId: number,
        origenIds: number[],
        asignar: boolean,
    ) =>
        setAsignaciones((actuales) => {
            const nuevas = new Map(actuales);

            for (const origenId of origenIds) {
                const bahiaId = porBahia ? destinoId : origenId;
                const usuarioId = porBahia ? origenId : destinoId;
                const usuarioIds = new Set(nuevas.get(bahiaId) ?? []);

                if (asignar) {
                    usuarioIds.add(usuarioId);
                } else {
                    usuarioIds.delete(usuarioId);
                }

                nuevas.set(bahiaId, usuarioIds);
            }

            return nuevas;
        });

    const limpiarSeleccion = () => {
        setSeleccion(new Set());
        setUltimoElegido(null);
    };

    const elegir = (evento: MouseEvent, origenId: number) => {
        const indice = origenesFiltrados.findIndex(
            (item) => item.id === origenId,
        );
        const indiceUltimo = origenesFiltrados.findIndex(
            (item) => item.id === ultimoElegido,
        );

        if (evento.shiftKey && indiceUltimo !== -1) {
            const [desde, hasta] = [indice, indiceUltimo].sort((a, b) => a - b);
            setSeleccion(
                (actual) =>
                    new Set([
                        ...actual,
                        ...origenesFiltrados
                            .slice(desde, hasta + 1)
                            .map((item) => item.id),
                    ]),
            );

            return;
        }

        if (evento.ctrlKey || evento.metaKey) {
            setSeleccion((actual) => {
                const nueva = new Set(actual);

                if (nueva.has(origenId)) {
                    nueva.delete(origenId);
                } else {
                    nueva.add(origenId);
                }

                return nueva;
            });
        } else {
            setSeleccion((actual) =>
                actual.size === 1 && actual.has(origenId)
                    ? new Set()
                    : new Set([origenId]),
            );
        }

        setUltimoElegido(origenId);
    };

    const empezarArrastre = (evento: DragEvent, origenId: number) => {
        // Si se arrastra uno que no estaba seleccionado, se arrastra solo ese.
        const ids = seleccion.has(origenId) ? [...seleccion] : [origenId];

        if (!seleccion.has(origenId)) {
            setSeleccion(new Set([origenId]));
            setUltimoElegido(origenId);
        }

        evento.dataTransfer.setData(TIPO_ARRASTRE, JSON.stringify(ids));
        evento.dataTransfer.effectAllowed = 'copy';
    };

    const soltar = (evento: DragEvent, destinoId: number) => {
        evento.preventDefault();
        setDestinoResaltado(null);

        const datos = evento.dataTransfer.getData(TIPO_ARRASTRE);

        if (!datos) {
            return;
        }

        cambiar(destinoId, JSON.parse(datos) as number[], true);
        limpiarSeleccion();
    };

    const invertir = () => {
        setModo((actual) =>
            actual === 'porBahia' ? 'porUsuario' : 'porBahia',
        );
        limpiarSeleccion();
        setBusquedaDestinos('');
        setBusquedaOrigenes('');
    };

    const cambiarApertura = (abierto: boolean) => {
        setOpen(abierto);

        if (abierto) {
            setAsignaciones(asignacionesIniciales(bahias));
            setModo('porBahia');
            limpiarSeleccion();
            setBusquedaDestinos('');
            setBusquedaOrigenes('');
            setErrores({});
        }
    };

    const guardar = () => {
        router.put(
            BahiaController.asignarUsuarios.url(),
            {
                asignaciones: [...asignaciones].map(
                    ([bahiaId, usuarioIds]) => ({
                        bahia_id: bahiaId,
                        usuario_ids: [...usuarioIds],
                    }),
                ),
            },
            {
                preserveScroll: true,
                onStart: () => setProcesando(true),
                onError: (errors) => setErrores(errors),
                onSuccess: () => setOpen(false),
                onFinish: () => setProcesando(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={cambiarApertura}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent
                className="sm:max-w-5xl"
                data-test="asignar-usuarios-bahias-modal"
            >
                <DialogHeader>
                    <DialogTitle>Asignar usuarios a bahías</DialogTitle>
                    <DialogDescription>
                        Arrastra {nombreOrigenes} de la derecha y suéltalos
                        sobre {porBahia ? 'una bahía' : 'un usuario'}.
                        Shift+clic selecciona un rango y Ctrl+clic (⌘ en Mac)
                        suma o quita uno, para arrastrar varios a la vez.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={invertir}
                        data-test="asignar-invertir"
                    >
                        <ArrowLeftRight />
                        {porBahia
                            ? 'Asignar bahías a usuarios'
                            : 'Asignar usuarios a bahías'}
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-[3fr_2fr]">
                    <section className="flex min-h-0 flex-col gap-2">
                        <h3 className="text-sm font-medium">
                            {nombreDestinos}
                        </h3>
                        <Buscador
                            value={busquedaDestinos}
                            onChange={setBusquedaDestinos}
                            placeholder={
                                porBahia
                                    ? 'Buscar bahía o área...'
                                    : 'Buscar usuario o correo...'
                            }
                        />
                        <ul
                            className="h-[26rem] space-y-2 overflow-y-auto pr-1"
                            data-test="asignar-destinos"
                        >
                            {destinosFiltrados.length === 0 ? (
                                <li className="p-3 text-sm text-muted-foreground">
                                    Sin resultados.
                                </li>
                            ) : null}
                            {destinosFiltrados.map((destino) => {
                                const asignados = asignadosDe(destino.id);

                                return (
                                    <li
                                        key={destino.id}
                                        onDragOver={(evento) => {
                                            if (
                                                evento.dataTransfer.types.includes(
                                                    TIPO_ARRASTRE,
                                                )
                                            ) {
                                                evento.preventDefault();
                                                evento.dataTransfer.dropEffect =
                                                    'copy';
                                                setDestinoResaltado(destino.id);
                                            }
                                        }}
                                        onDragLeave={() =>
                                            setDestinoResaltado((actual) =>
                                                actual === destino.id
                                                    ? null
                                                    : actual,
                                            )
                                        }
                                        onDrop={(evento) =>
                                            soltar(evento, destino.id)
                                        }
                                        className={cn(
                                            'rounded-md border border-dashed p-3 transition-colors',
                                            destinoResaltado === destino.id &&
                                                'border-primary bg-primary/10',
                                        )}
                                        data-test="asignar-destino"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {destino.titulo}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {destino.detalle}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {seleccion.size > 0 ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7"
                                                        onClick={() => {
                                                            cambiar(
                                                                destino.id,
                                                                [...seleccion],
                                                                true,
                                                            );
                                                            limpiarSeleccion();
                                                        }}
                                                    >
                                                        <Plus /> Asignar{' '}
                                                        {seleccion.size}
                                                    </Button>
                                                ) : null}
                                                <Badge
                                                    variant={
                                                        asignados.length > 0
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {asignados.length}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {asignados.length === 0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Suelta aquí {nombreOrigenes}
                                                </span>
                                            ) : null}
                                            {asignados.map((origen) => (
                                                <Badge
                                                    key={origen.id}
                                                    variant="outline"
                                                    className="gap-1 pr-1"
                                                >
                                                    {origen.titulo}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            cambiar(
                                                                destino.id,
                                                                [origen.id],
                                                                false,
                                                            )
                                                        }
                                                        className="rounded-sm p-0.5 hover:bg-muted"
                                                        aria-label={`Quitar ${origen.titulo} de ${destino.titulo}`}
                                                    >
                                                        <X className="size-3" />
                                                    </button>
                                                </Badge>
                                            ))}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section className="flex min-h-0 flex-col gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-sm font-medium capitalize">
                                {nombreOrigenes}
                            </h3>
                            <div className="flex items-center gap-1 text-xs">
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    className="h-auto p-0 text-xs"
                                    onClick={() =>
                                        setSeleccion(
                                            new Set(
                                                origenesFiltrados.map(
                                                    (item) => item.id,
                                                ),
                                            ),
                                        )
                                    }
                                >
                                    Seleccionar{' '}
                                    {busquedaOrigenes.trim()
                                        ? 'filtrados'
                                        : 'todos'}
                                </Button>
                                {seleccion.size > 0 ? (
                                    <>
                                        <span className="text-muted-foreground">
                                            ·
                                        </span>
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            className="h-auto p-0 text-xs"
                                            onClick={limpiarSeleccion}
                                        >
                                            Quitar selección ({seleccion.size})
                                        </Button>
                                    </>
                                ) : null}
                            </div>
                        </div>
                        <Buscador
                            value={busquedaOrigenes}
                            onChange={setBusquedaOrigenes}
                            placeholder={
                                porBahia
                                    ? 'Buscar usuario o correo...'
                                    : 'Buscar bahía o área...'
                            }
                        />
                        <ul
                            className="h-[26rem] divide-y overflow-y-auto rounded-md border select-none"
                            data-test="asignar-origenes"
                        >
                            {origenesFiltrados.length === 0 ? (
                                <li className="p-3 text-sm text-muted-foreground">
                                    Sin resultados.
                                </li>
                            ) : null}
                            {origenesFiltrados.map((origen) => {
                                const seleccionado = seleccion.has(origen.id);
                                const cantidad = cantidadDestinos(origen.id);

                                return (
                                    <li
                                        key={origen.id}
                                        draggable
                                        onDragStart={(evento) =>
                                            empezarArrastre(evento, origen.id)
                                        }
                                        onDragEnd={() =>
                                            setDestinoResaltado(null)
                                        }
                                        onClick={(evento) =>
                                            elegir(evento, origen.id)
                                        }
                                        aria-selected={seleccionado}
                                        className={cn(
                                            'flex cursor-grab items-center gap-2 px-2 py-2 active:cursor-grabbing',
                                            seleccionado
                                                ? 'bg-primary/15'
                                                : 'hover:bg-muted',
                                        )}
                                        data-test="asignar-origen"
                                    >
                                        <GripVertical className="size-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium">
                                                {origen.titulo}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {origen.detalle}
                                            </span>
                                        </span>
                                        <Badge
                                            variant={
                                                cantidad > 0
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            title={`Asignado a ${cantidad} ${porBahia ? 'bahías' : 'usuarios'}`}
                                        >
                                            {cantidad}
                                        </Badge>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                </div>

                <InputError message={Object.values(errores)[0]} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={procesando}>
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={guardar}
                        loading={procesando}
                        data-test="asignar-usuarios-bahias-guardar"
                    >
                        Guardar asignaciones
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
