import { usePage } from '@inertiajs/react';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import { ExternalLink, Gauge, RefreshCw, Truck, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { eventos } from '@/actions/App/Http/Controllers/AgendaController';
import AgendaFiltroTecnicos from '@/components/agenda/agenda-filtro-tecnicos';
import type { TecnicoAgenda } from '@/components/agenda/agenda-filtro-tecnicos';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type TipoAgenda = 'MANTENIMIENTO' | 'CALIBRACION' | 'DESPACHO';

type EventoAgenda = {
    id: string;
    start: string;
    tipo: TipoAgenda;
    estado: string;
    orden_trabajo: string;
    equipo: string;
    modelo: string | null;
    cliente: string | null;
    tecnico: string | null;
    tecnico_id: number | null;
    ruta: string;
};

type EntradaCache = {
    clave: string;
    guardado: number;
    datos: EventoAgenda[];
};

/** Cuánto tiempo se reutilizan en el dispositivo los eventos ya descargados. */
const VIGENCIA_CACHE_MS = 5 * 60 * 1000;

let cacheEnMemoria: EntradaCache | null = null;

function esVigente(entrada: EntradaCache | null, clave: string) {
    return (
        entrada !== null &&
        entrada.clave === clave &&
        Date.now() - entrada.guardado < VIGENCIA_CACHE_MS
    );
}

function leerCache(clave: string): EventoAgenda[] | null {
    if (esVigente(cacheEnMemoria, clave)) {
        return cacheEnMemoria!.datos;
    }

    try {
        const guardado = localStorage.getItem(clave);
        const entrada = guardado
            ? (JSON.parse(guardado) as EntradaCache)
            : null;

        if (esVigente(entrada, clave)) {
            cacheEnMemoria = entrada;

            return entrada!.datos;
        }
    } catch {
        // Sin almacenamiento local disponible: se pide al servidor.
    }

    return null;
}

function guardarCache(clave: string, datos: EventoAgenda[]) {
    cacheEnMemoria = { clave, guardado: Date.now(), datos };

    try {
        localStorage.setItem(clave, JSON.stringify(cacheEnMemoria));
    } catch {
        // Sin almacenamiento local disponible: solo queda en memoria.
    }
}

const tipos: Record<
    TipoAgenda,
    { titulo: string; icono: LucideIcon; clases: string; punto: string }
> = {
    MANTENIMIENTO: {
        titulo: 'Mantenimiento',
        icono: Wrench,
        clases: '!border-l-4 !border-amber-500 !bg-amber-100 !text-amber-900 dark:!bg-amber-500/20 dark:!text-amber-200',
        punto: 'bg-amber-500',
    },
    CALIBRACION: {
        titulo: 'Calibración',
        icono: Gauge,
        clases: '!border-l-4 !border-sky-500 !bg-sky-100 !text-sky-900 dark:!bg-sky-500/20 dark:!text-sky-200',
        punto: 'bg-sky-500',
    },
    DESPACHO: {
        titulo: 'Despacho',
        icono: Truck,
        clases: '!border-l-4 !border-emerald-500 !bg-emerald-100 !text-emerald-900 dark:!bg-emerald-500/20 dark:!text-emerald-200',
        punto: 'bg-emerald-500',
    },
};

/** Mismos colores que los estados de cada módulo: pendiente amarillo, en proceso azul, problema rojo, listo verde. */
const estados: Record<string, { titulo: string; clases: string }> = {
    PENDIENTE: {
        titulo: 'Pendiente',
        clases: '!border-transparent !bg-amber-500 !text-white',
    },
    EN_PROCESO: {
        titulo: 'En proceso',
        clases: '!border-transparent !bg-blue-500 !text-white',
    },
    FALTA_REPUESTOS: {
        titulo: 'Falta repuestos',
        clases: '!border-transparent !bg-red-500 !text-white',
    },
    AUTORIZADO: {
        titulo: 'Autorizado',
        clases: '!border-transparent !bg-sky-500 !text-white',
    },
};

const estadoPorDefecto = {
    clases: '!border-transparent !bg-slate-500 !text-white',
};

const formatoDia = new Intl.DateTimeFormat('es', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/**
 * Calendario con los mantenimientos, calibraciones y despachos pendientes. Los eventos se
 * piden una sola vez y se guardan en el dispositivo: cambiar de mes o de técnico no vuelve
 * a llamar al servidor. Se renuevan al vencer la vigencia o con el botón "Actualizar".
 * Al hacer clic en un día o en un evento, debajo se listan las tareas de ese día.
 */
export default function AgendaCalendario() {
    const { auth } = usePage().props;
    const clave = `agenda-eventos-${auth.user.id}`;
    const [cargados, setCargados] = useState<EventoAgenda[]>([]);
    const [ocultos, setOcultos] = useState<TipoAgenda[]>([]);
    const [tecnicosElegidos, setTecnicosElegidos] = useState<number[]>([]);
    const [cargando, setCargando] = useState(false);
    const [fallo, setFallo] = useState(false);
    const [seleccion, setSeleccion] = useState<{
        dia: string;
        n: number;
    } | null>(null);
    const detalleRef = useRef<HTMLDivElement>(null);
    const dia = seleccion?.dia ?? null;

    // Cada selección (incluso del mismo día) lleva la vista hasta el detalle.
    useEffect(() => {
        if (seleccion) {
            detalleRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        }
    }, [seleccion]);

    const seleccionar = (fecha: string) =>
        setSeleccion((actual) => ({ dia: fecha, n: (actual?.n ?? 0) + 1 }));

    const cargar = useCallback(
        async (forzar: boolean) => {
            const guardados = forzar ? null : leerCache(clave);

            if (guardados) {
                setCargados(guardados);

                return;
            }

            setCargando(true);
            setFallo(false);

            try {
                const respuesta = await fetch(eventos().url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!respuesta.ok) {
                    throw new Error(String(respuesta.status));
                }

                const datos = (await respuesta.json()) as EventoAgenda[];

                guardarCache(clave, datos);
                setCargados(datos);
            } catch {
                setFallo(true);
            } finally {
                setCargando(false);
            }
        },
        [clave],
    );

    useEffect(() => {
        void cargar(false);
    }, [cargar]);

    // Los técnicos ofrecidos son los que tienen alguna tarea pendiente en la agenda.
    const tecnicos = useMemo<TecnicoAgenda[]>(() => {
        const porId = new Map<number, string>();

        cargados.forEach((evento) => {
            if (evento.tecnico_id !== null && evento.tecnico) {
                porId.set(evento.tecnico_id, evento.tecnico);
            }
        });

        return [...porId.entries()]
            .map(([id, nombre]) => ({ id, nombre }))
            .sort((a, b) => a.nombre.localeCompare(b.nombre));
    }, [cargados]);

    const visibles = cargados.filter(
        (evento) =>
            !ocultos.includes(evento.tipo) &&
            (tecnicosElegidos.length === 0 ||
                (evento.tecnico_id !== null &&
                    tecnicosElegidos.includes(evento.tecnico_id))),
    );
    const delDia = visibles.filter((evento) => evento.start === dia);

    const alternarTipo = (tipo: TipoAgenda) =>
        setOcultos((actuales) =>
            actuales.includes(tipo)
                ? actuales.filter((valor) => valor !== tipo)
                : [...actuales, tipo],
        );

    return (
        <div className="space-y-4" data-test="agenda-calendario">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <AgendaFiltroTecnicos
                    tecnicos={tecnicos}
                    seleccionados={tecnicosElegidos}
                    onChange={setTecnicosElegidos}
                />

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={cargando}
                    onClick={() => void cargar(true)}
                    data-test="agenda-actualizar"
                >
                    <RefreshCw
                        className={`size-4 ${cargando ? 'animate-spin' : ''}`}
                    />{' '}
                    Actualizar
                </Button>
            </div>

            {fallo ? (
                <p className="text-sm text-destructive">
                    No se pudo cargar la agenda. Intenta actualizar.
                </p>
            ) : null}

            <div
                className="flex flex-wrap gap-2 text-xs"
                role="group"
                aria-label="Filtrar por tipo de tarea"
            >
                {(Object.keys(tipos) as TipoAgenda[]).map((tipo) => {
                    const activo = !ocultos.includes(tipo);

                    return (
                        <button
                            key={tipo}
                            type="button"
                            aria-pressed={activo}
                            onClick={() => alternarTipo(tipo)}
                            data-test={`agenda-leyenda-${tipo.toLowerCase()}`}
                            className={`flex items-center gap-1.5 rounded-full border px-3 py-1 font-medium transition-colors ${
                                activo
                                    ? 'bg-background text-foreground hover:bg-muted'
                                    : 'bg-muted/40 text-muted-foreground line-through opacity-60 hover:opacity-100'
                            }`}
                        >
                            <span
                                className={`size-2.5 rounded-full ${
                                    activo
                                        ? tipos[tipo].punto
                                        : 'bg-muted-foreground/40'
                                }`}
                            />
                            {tipos[tipo].titulo}
                        </button>
                    );
                })}
            </div>
            <div className="agenda-cal rounded-xl border bg-card p-3 shadow-sm sm:p-4">
                <FullCalendar
                    plugins={[dayGridPlugin, listPlugin, interactionPlugin]}
                    locale={esLocale}
                    initialView="dayGridMonth"
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,dayGridWeek,listMonth',
                    }}
                    height="auto"
                    fixedWeekCount={false}
                    firstDay={1}
                    buttonText={{
                        today: 'Hoy',
                        month: 'Mes',
                        week: 'Semana',
                        list: 'Lista',
                    }}
                    dayMaxEvents={3}
                    events={visibles}
                    eventClassNames={({ event }) =>
                        tipos[event.extendedProps.tipo as TipoAgenda].clases
                    }
                    eventContent={({ event }) => {
                        const Icono =
                            tipos[event.extendedProps.tipo as TipoAgenda].icono;

                        return (
                            <div className="flex items-center gap-1.5 overflow-hidden px-1.5 py-0.5 text-xs font-medium">
                                <Icono className="size-3 shrink-0" />
                                <span className="truncate">
                                    {event.extendedProps.equipo}
                                    {event.extendedProps.cliente
                                        ? ` · ${event.extendedProps.cliente}`
                                        : ''}
                                </span>
                            </div>
                        );
                    }}
                    dateClick={(info) => seleccionar(info.dateStr.slice(0, 10))}
                    eventClick={(info) =>
                        seleccionar(info.event.startStr.slice(0, 10))
                    }
                />
            </div>

            {dia ? (
                <div
                    ref={detalleRef}
                    className="scroll-mb-4 space-y-3 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <h3 className="text-sm font-semibold first-letter:uppercase">
                        {formatoDia.format(new Date(`${dia}T00:00:00`))}
                    </h3>

                    {delDia.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No hay tareas pendientes este día.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {delDia.map((evento) => {
                                const tipo = tipos[evento.tipo];

                                return (
                                    <li
                                        key={evento.id}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-background p-3 text-sm transition-colors hover:bg-muted/40"
                                        data-test="agenda-tarea"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    className={`${tipo.clases} gap-1`}
                                                >
                                                    <tipo.icono className="size-3" />
                                                    {tipo.titulo}
                                                </Badge>
                                                <span className="font-medium">
                                                    {evento.equipo}
                                                    {evento.modelo
                                                        ? ` · ${evento.modelo}`
                                                        : ''}
                                                </span>
                                                <Badge
                                                    className={
                                                        (
                                                            estados[
                                                                evento.estado
                                                            ] ??
                                                            estadoPorDefecto
                                                        ).clases
                                                    }
                                                    data-test="agenda-estado-badge"
                                                >
                                                    {estados[evento.estado]
                                                        ?.titulo ??
                                                        evento.estado}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground">
                                                {evento.cliente ??
                                                    'Sin cliente'}{' '}
                                                · Orden {evento.orden_trabajo} ·
                                                Técnico:{' '}
                                                {evento.tecnico ??
                                                    'sin asignar'}{' '}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a href={evento.ruta}>
                                                <ExternalLink className="size-4" />{' '}
                                                Abrir módulo
                                            </a>
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Haz clic en un día o en una tarea para ver el detalle.
                </p>
            )}
        </div>
    );
}
