import { useHttp } from '@inertiajs/react';
import { Eye, Paperclip, Plus, Printer } from 'lucide-react';
import {
    type PropsWithChildren,
    type ReactNode,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { certificado } from '@/actions/App/Modules/Calibraciones/Http/Controllers/CalibracionController';
import { historial } from '@/actions/App/Modules/Equipos/Http/Controllers/EquipoController';
import { informe } from '@/actions/App/Modules/Mantenimientos/Http/Controllers/MantenimientoController';
import VerCalibracionModal from '@/components/calibraciones/ver-calibracion-modal';
import CargandoContenido from '@/components/cargando-contenido';
import AgregarHistoricoModal from '@/components/equipos/agregar-historico-modal';
import VerDespachoModal from '@/components/despachos/ver-despacho-modal';
import { estadosProgramacion } from '@/components/equipos/equipo-programacion-fields';
import VerIngresoModal from '@/components/ingresos/ver-ingreso-modal';
import VerMantenimientoModal from '@/components/mantenimientos/ver-mantenimiento-modal';
import VerServicioTerceroModal from '@/components/servicio-terceros/ver-servicio-tercero-modal';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { colorEstadoCalibracion } from '@/lib/estados-calibracion';
import { colorEstadoIngreso, estadosIngreso } from '@/lib/estados-ingreso';
import {
    colorEstadoMantenimiento,
    estadosMantenimiento,
} from '@/lib/estados-mantenimiento';
import {
    fechaEnRango,
    type Periodo,
    periodos,
    rangoDelPeriodo,
} from '@/lib/periodo-historial';
import type { Equipo, HistorialEquipo } from '@/types';

type Props = {
    equipo: Equipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Registro del historial cuya modal "Ver" está abierta. */
type Detalle = {
    tipo:
        | 'ingreso'
        | 'mantenimiento'
        | 'calibracion'
        | 'despacho'
        | 'servicio_tercero';
    id: number;
};

const HISTORIAL_VACIO: HistorialEquipo = {
    ingresos: [],
    mantenimientos: [],
    calibraciones: [],
    despachos: [],
    servicios_terceros: [],
    historicos: [],
    novedadesIngreso: [],
    laboratorioPropio: null,
    empresasTerceras: [],
};

/** Colores para distinguir de un vistazo los históricos propios de los de terceros. */
const BADGE_PROPIO = '!border-transparent !bg-sky-600 !text-white';
const BADGE_TERCERO = '!border-transparent !bg-violet-600 !text-white';

const etiquetasEstadoCalibracion: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    EN_PROCESO: 'En proceso',
    FINALIZADO: 'Finalizado',
    DEVOLVER_MANTENIMIENTO: 'Devolver a mantenimiento',
    FUERA_DE_SERVICIO: 'Fuera de servicio',
};

const etiquetasEstadoDespacho: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    AUTORIZADO: 'Autorizado',
    ENTREGADO: 'Entregado',
};

/** Pendiente en amarillo, autorizado en azul, entregado en verde. */
const colorEstadoDespacho: Record<string, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    AUTORIZADO: '!border-transparent !bg-blue-500 !text-white',
    ENTREGADO: '!border-transparent !bg-emerald-500 !text-white',
};

const etiquetasEstadoServicio: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    EN_PROCESO: 'En proceso',
    FINALIZADO: 'Finalizado',
};

/** Pendiente en amarillo, en proceso en azul, finalizado en verde. */
const colorEstadoServicio: Record<string, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    EN_PROCESO: '!border-transparent !bg-blue-500 !text-white',
    FINALIZADO: '!border-transparent !bg-emerald-500 !text-white',
};

function etiqueta(
    opciones: { value: string; label: string }[],
    valor: string,
): string {
    return opciones.find((opcion) => opcion.value === valor)?.label ?? valor;
}

function Dato({ label, value }: { label: string; value: ReactNode }) {
    return (
        <span>
            <span className="text-muted-foreground">{label}: </span>
            {value === null || value === undefined || value === ''
                ? '—'
                : value}
        </span>
    );
}

function Tarjeta({
    titulo,
    badge,
    acciones,
    onVer,
    children,
}: PropsWithChildren<{
    titulo: string;
    badge: ReactNode;
    acciones?: ReactNode;
    /** Abre la modal "Ver" del registro; sin ella no se muestra el botón. */
    onVer?: () => void;
}>) {
    return (
        <li
            className="space-y-2 rounded-lg border p-3"
            data-test="historial-equipo-item"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-medium">{titulo}</span>
                <div className="flex items-center gap-2">
                    {badge}
                    {acciones}
                    {onVer ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onVer}
                            data-test="historial-equipo-ver"
                        >
                            <Eye className="h-4 w-4" /> Ver
                        </Button>
                    ) : null}
                </div>
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                {children}
            </div>
        </li>
    );
}

function Lista({ vacio, children }: PropsWithChildren<{ vacio: string }>) {
    const items = Array.isArray(children) ? children : [children];

    if (items.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {vacio}
            </p>
        );
    }

    return <ul className="space-y-2">{children}</ul>;
}

function BotonPdf({ href }: { href: string }) {
    return (
        <Button variant="outline" size="sm" asChild>
            <a href={href} target="_blank" rel="noreferrer">
                <Printer className="h-4 w-4" /> PDF
            </a>
        </Button>
    );
}

/**
 * Historial de solo lectura de un equipo: ingresos, mantenimientos, calibraciones,
 * despachos y servicios de terceros, del más reciente al más antiguo. Se pide al
 * servidor cada vez que se abre. "Ver" abre la misma modal de detalle de cada módulo.
 */
export default function HistorialEquipoModal({
    equipo,
    open,
    onOpenChange,
}: Props) {
    const { can } = usePermissions();
    const [datos, setDatos] = useState<HistorialEquipo | null>(null);
    const [detalle, setDetalle] = useState<Detalle | null>(null);
    const [detalleOpen, setDetalleOpen] = useState(false);
    const [periodo, setPeriodo] = useState<Periodo>('TODO');
    const [personalizado, setPersonalizado] = useState({
        desde: '',
        hasta: '',
    });
    const [pestana, setPestana] = useState('ingresos');
    const [agregarOpen, setAgregarOpen] = useState(false);
    const { get } = useHttp<Record<string, never>, HistorialEquipo>();

    useEffect(() => {
        if (!open || !equipo) {
            return;
        }

        setDatos(null);
        setPestana('ingresos');
        setPeriodo('TODO');
        setPersonalizado({ desde: '', hasta: '' });
        get(historial.url(equipo.id))
            .then(setDatos)
            .catch(() => setDatos(HISTORIAL_VACIO));
        // Solo al abrir la modal o cambiar de equipo.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, equipo?.id]);

    /** Registros dentro del periodo elegido, según la fecha principal de cada uno. */
    const filtrados = useMemo((): HistorialEquipo => {
        const historialCompleto = datos ?? HISTORIAL_VACIO;
        const rango = rangoDelPeriodo(periodo, new Date(), personalizado);

        return {
            ...historialCompleto,
            ingresos: historialCompleto.ingresos.filter((ingreso) =>
                fechaEnRango(ingreso.fecha_recepcion ?? ingreso.desde, rango),
            ),
            mantenimientos: historialCompleto.mantenimientos.filter(
                (mantenimiento) =>
                    fechaEnRango(mantenimiento.fecha_mantenimiento, rango),
            ),
            calibraciones: historialCompleto.calibraciones.filter(
                (calibracion) =>
                    fechaEnRango(calibracion.fecha_calibracion, rango),
            ),
            despachos: historialCompleto.despachos.filter((despacho) =>
                fechaEnRango(
                    despacho.fecha_despacho ?? despacho.fecha_programada,
                    rango,
                ),
            ),
            servicios_terceros: historialCompleto.servicios_terceros.filter(
                (servicio) => fechaEnRango(servicio.fecha, rango),
            ),
            historicos: historialCompleto.historicos.filter((historico) =>
                fechaEnRango(historico.fecha_servicio, rango),
            ),
        };
    }, [datos, periodo, personalizado]);

    const ver = (tipo: Detalle['tipo'], id: number) => {
        setDetalle({ tipo, id });
        setDetalleOpen(true);
    };

    /** El registro del tipo indicado cuya modal "Ver" se pidió, o null. */
    const detalleDe = <T extends { id: number }>(
        tipo: Detalle['tipo'],
        registros: T[] | undefined,
    ): T | null =>
        detalle?.tipo === tipo
            ? (registros?.find((registro) => registro.id === detalle.id) ??
              null)
            : null;

    const abiertoSi = (tipo: Detalle['tipo']) =>
        detalleOpen && detalle?.tipo === tipo;

    /** Secciones del historial, en el orden de las pestañas (o del desplegable en el celular). */
    const secciones = [
        {
            value: 'ingresos',
            etiqueta: 'Ingresos',
            cantidad: filtrados.ingresos.length,
        },
        {
            value: 'mantenimientos',
            etiqueta: 'Mantenimientos',
            cantidad: filtrados.mantenimientos.length,
        },
        {
            value: 'calibraciones',
            etiqueta: 'Calibraciones',
            cantidad: filtrados.calibraciones.length,
        },
        {
            value: 'despachos',
            etiqueta: 'Despachos',
            cantidad: filtrados.despachos.length,
        },
        {
            value: 'terceros',
            etiqueta: 'Terceros',
            cantidad: filtrados.servicios_terceros.length,
        },
        {
            value: 'historico',
            etiqueta: 'Histórico',
            cantidad: filtrados.historicos.length,
        },
    ];

    const estadoDelEquipoEnIngreso = (
        ingreso: HistorialEquipo['ingresos'][number],
    ) =>
        ingreso.equipo_programaciones.find(
            (programacion) => programacion.equipo.id === equipo?.id,
        )?.estado_programacion;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent
                    className="max-h-[85vh] overflow-y-auto sm:max-w-3xl"
                    data-test="historial-equipo-modal"
                >
                    <DialogHeader>
                        <DialogTitle>
                            Historial del equipo {equipo?.codigo}
                        </DialogTitle>
                        <DialogDescription>
                            Ingresos, mantenimientos, calibraciones, despachos,
                            servicios de terceros e histórico importado del
                            equipo.
                        </DialogDescription>
                    </DialogHeader>

                    {datos === null ? (
                        <CargandoContenido mensaje="Cargando historial..." />
                    ) : (
                        <div className="space-y-4">
                            <div
                                className="space-y-3"
                                data-test="historial-equipo-filtro"
                            >
                                {/* Periodo y "Agregar historial" siempre en la misma fila, también en el celular. */}
                                <div className="flex items-end gap-3">
                                    <div className="grid min-w-0 flex-1 gap-1.5 sm:flex-none">
                                        <Label htmlFor="historial-periodo">
                                            Periodo
                                        </Label>
                                        <Select
                                            value={periodo}
                                            onValueChange={(valor) =>
                                                setPeriodo(valor as Periodo)
                                            }
                                        >
                                            <SelectTrigger
                                                id="historial-periodo"
                                                className="w-full sm:w-48"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {periodos.map((opcion) => (
                                                    <SelectItem
                                                        key={opcion.value}
                                                        value={opcion.value}
                                                    >
                                                        {opcion.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {can('equipos.historico-agregar') ? (
                                        <Button
                                            type="button"
                                            className="ml-auto shrink-0"
                                            onClick={() => setAgregarOpen(true)}
                                            data-test="historial-agregar-historico"
                                        >
                                            <Plus className="h-4 w-4" /> Agregar
                                            historial
                                        </Button>
                                    ) : null}
                                </div>
                                {periodo === 'PERSONALIZADO' ? (
                                    <div className="grid grid-cols-2 gap-3 sm:flex">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="historial-desde">
                                                Desde
                                            </Label>
                                            <Input
                                                id="historial-desde"
                                                type="date"
                                                className="w-full sm:w-40"
                                                value={personalizado.desde}
                                                max={
                                                    personalizado.hasta ||
                                                    undefined
                                                }
                                                onChange={(event) =>
                                                    setPersonalizado(
                                                        (actual) => ({
                                                            ...actual,
                                                            desde: event.target
                                                                .value,
                                                        }),
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="historial-hasta">
                                                Hasta
                                            </Label>
                                            <Input
                                                id="historial-hasta"
                                                type="date"
                                                className="w-full sm:w-40"
                                                value={personalizado.hasta}
                                                min={
                                                    personalizado.desde ||
                                                    undefined
                                                }
                                                onChange={(event) =>
                                                    setPersonalizado(
                                                        (actual) => ({
                                                            ...actual,
                                                            hasta: event.target
                                                                .value,
                                                        }),
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                            <Tabs value={pestana} onValueChange={setPestana}>
                                {/* En el celular las secciones no caben como pestañas: se elige en un desplegable. */}
                                <div className="grid gap-1.5 sm:hidden">
                                    <Label htmlFor="historial-seccion">
                                        Sección
                                    </Label>
                                    <Select
                                        value={pestana}
                                        onValueChange={setPestana}
                                    >
                                        <SelectTrigger
                                            id="historial-seccion"
                                            className="w-full"
                                            data-test="historial-seccion"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {secciones.map((seccion) => (
                                                <SelectItem
                                                    key={seccion.value}
                                                    value={seccion.value}
                                                >
                                                    {seccion.etiqueta} (
                                                    {seccion.cantidad})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <TabsList className="hidden h-auto w-full justify-start overflow-x-auto sm:flex">
                                    {secciones.map((seccion) => (
                                        <TabsTrigger
                                            key={seccion.value}
                                            value={seccion.value}
                                            className="shrink-0"
                                        >
                                            {seccion.etiqueta} (
                                            {seccion.cantidad})
                                        </TabsTrigger>
                                    ))}
                                </TabsList>

                                <TabsContent value="ingresos" className="pt-2">
                                    <Lista vacio="El equipo aún no ha tenido ingresos.">
                                        {filtrados.ingresos.map((ingreso) => (
                                            <Tarjeta
                                                key={ingreso.id}
                                                titulo={`Ingreso del ${ingreso.desde} al ${ingreso.hasta}`}
                                                onVer={() =>
                                                    ver('ingreso', ingreso.id)
                                                }
                                                badge={
                                                    <Badge
                                                        className={
                                                            colorEstadoIngreso[
                                                                ingreso
                                                                    .estado_ingreso
                                                            ]
                                                        }
                                                    >
                                                        {etiqueta(
                                                            estadosIngreso,
                                                            ingreso.estado_ingreso,
                                                        )}
                                                    </Badge>
                                                }
                                            >
                                                <Dato
                                                    label="Recepción"
                                                    value={
                                                        ingreso.fecha_recepcion
                                                    }
                                                />
                                                <Dato
                                                    label="Bahías"
                                                    value={
                                                        ingreso.bahias_nombre
                                                    }
                                                />
                                                <Dato
                                                    label="Equipo"
                                                    value={etiqueta(
                                                        estadosProgramacion,
                                                        estadoDelEquipoEnIngreso(
                                                            ingreso,
                                                        ) ?? '',
                                                    )}
                                                />
                                            </Tarjeta>
                                        ))}
                                    </Lista>
                                </TabsContent>

                                <TabsContent
                                    value="mantenimientos"
                                    className="pt-2"
                                >
                                    <Lista vacio="El equipo aún no tiene mantenimientos.">
                                        {filtrados.mantenimientos.map(
                                            (mantenimiento) => (
                                                <Tarjeta
                                                    key={mantenimiento.id}
                                                    titulo={`${mantenimiento.orden_trabajo_codigo} · ${mantenimiento.fecha_mantenimiento}`}
                                                    onVer={() =>
                                                        ver(
                                                            'mantenimiento',
                                                            mantenimiento.id,
                                                        )
                                                    }
                                                    badge={
                                                        <Badge
                                                            className={
                                                                colorEstadoMantenimiento[
                                                                    mantenimiento
                                                                        .estado_mantenimiento
                                                                ]
                                                            }
                                                        >
                                                            {etiqueta(
                                                                estadosMantenimiento,
                                                                mantenimiento.estado_mantenimiento,
                                                            )}
                                                        </Badge>
                                                    }
                                                    acciones={
                                                        can(
                                                            'mantenimientos.pdf',
                                                        ) &&
                                                        mantenimiento.estado_mantenimiento ===
                                                            'FINALIZADO' ? (
                                                            <BotonPdf
                                                                href={informe.url(
                                                                    mantenimiento.id,
                                                                )}
                                                            />
                                                        ) : null
                                                    }
                                                >
                                                    <Dato
                                                        label="Tipo"
                                                        value={
                                                            mantenimiento.tipo_mantenimiento
                                                        }
                                                    />
                                                    <Dato
                                                        label="Técnico"
                                                        value={
                                                            mantenimiento.tecnico_nombre
                                                        }
                                                    />
                                                </Tarjeta>
                                            ),
                                        )}
                                    </Lista>
                                </TabsContent>

                                <TabsContent
                                    value="calibraciones"
                                    className="pt-2"
                                >
                                    <Lista vacio="El equipo aún no tiene calibraciones.">
                                        {filtrados.calibraciones.map(
                                            (calibracion) => (
                                                <Tarjeta
                                                    key={calibracion.id}
                                                    titulo={`${calibracion.orden_trabajo_codigo} · ${calibracion.fecha_calibracion}`}
                                                    onVer={() =>
                                                        ver(
                                                            'calibracion',
                                                            calibracion.id,
                                                        )
                                                    }
                                                    badge={
                                                        <Badge
                                                            className={
                                                                colorEstadoCalibracion[
                                                                    calibracion
                                                                        .estado_calibracion
                                                                ]
                                                            }
                                                        >
                                                            {etiquetasEstadoCalibracion[
                                                                calibracion
                                                                    .estado_calibracion
                                                            ] ??
                                                                calibracion.estado_calibracion}
                                                        </Badge>
                                                    }
                                                    acciones={
                                                        can(
                                                            'calibraciones.pdf',
                                                        ) &&
                                                        calibracion.estado_calibracion ===
                                                            'FINALIZADO' ? (
                                                            <BotonPdf
                                                                href={certificado.url(
                                                                    calibracion.id,
                                                                )}
                                                            />
                                                        ) : null
                                                    }
                                                >
                                                    <Dato
                                                        label="Resultado"
                                                        value={
                                                            calibracion.resultado_calibracion ===
                                                            null
                                                                ? null
                                                                : calibracion.resultado_calibracion
                                                                  ? 'Aprobado'
                                                                  : 'No aprobado'
                                                        }
                                                    />
                                                    <Dato
                                                        label="Laboratorio"
                                                        value={
                                                            calibracion.laboratorio_nombre
                                                        }
                                                    />
                                                    <Dato
                                                        label="Técnico"
                                                        value={
                                                            calibracion.tecnico_nombre
                                                        }
                                                    />
                                                </Tarjeta>
                                            ),
                                        )}
                                    </Lista>
                                </TabsContent>

                                <TabsContent value="despachos" className="pt-2">
                                    <Lista vacio="El equipo aún no tiene despachos.">
                                        {filtrados.despachos.map((despacho) => (
                                            <Tarjeta
                                                key={despacho.id}
                                                titulo={
                                                    despacho.orden_trabajo_codigo
                                                }
                                                onVer={() =>
                                                    ver('despacho', despacho.id)
                                                }
                                                badge={
                                                    <Badge
                                                        className={
                                                            colorEstadoDespacho[
                                                                despacho
                                                                    .estado_despacho
                                                            ]
                                                        }
                                                    >
                                                        {etiquetasEstadoDespacho[
                                                            despacho
                                                                .estado_despacho
                                                        ] ??
                                                            despacho.estado_despacho}
                                                    </Badge>
                                                }
                                            >
                                                <Dato
                                                    label="Programado"
                                                    value={
                                                        despacho.fecha_programada
                                                    }
                                                />
                                                <Dato
                                                    label="Entregado"
                                                    value={
                                                        despacho.fecha_despacho
                                                    }
                                                />
                                                <Dato
                                                    label="Técnico"
                                                    value={
                                                        despacho.tecnico_entrega_nombre
                                                    }
                                                />
                                                <Dato
                                                    label="Recibió"
                                                    value={
                                                        despacho.cliente_recibe_nombre
                                                    }
                                                />
                                            </Tarjeta>
                                        ))}
                                    </Lista>
                                </TabsContent>

                                <TabsContent value="terceros" className="pt-2">
                                    <Lista vacio="El equipo aún no tiene servicios de terceros.">
                                        {filtrados.servicios_terceros.map(
                                            (servicio) => (
                                                <Tarjeta
                                                    key={servicio.id}
                                                    titulo={`${servicio.orden_trabajo_codigo} · ${servicio.fecha}`}
                                                    onVer={() =>
                                                        ver(
                                                            'servicio_tercero',
                                                            servicio.id,
                                                        )
                                                    }
                                                    badge={
                                                        <Badge
                                                            className={
                                                                colorEstadoServicio[
                                                                    servicio
                                                                        .estado_servicio
                                                                ]
                                                            }
                                                        >
                                                            {etiquetasEstadoServicio[
                                                                servicio
                                                                    .estado_servicio
                                                            ] ??
                                                                servicio.estado_servicio}
                                                        </Badge>
                                                    }
                                                    acciones={
                                                        servicio.pdf_url ? (
                                                            <BotonPdf
                                                                href={
                                                                    servicio.pdf_url
                                                                }
                                                            />
                                                        ) : null
                                                    }
                                                >
                                                    <Dato
                                                        label="Servicio"
                                                        value={
                                                            servicio.tipo_servicio ===
                                                            'MANTENIMIENTO'
                                                                ? 'Mantenimiento'
                                                                : 'Calibración'
                                                        }
                                                    />
                                                    <Dato
                                                        label="Empresa"
                                                        value={
                                                            servicio.empresa_tercero_nombre
                                                        }
                                                    />
                                                    <Dato
                                                        label="Resultado"
                                                        value={
                                                            servicio.estado_final_equipo ===
                                                            null
                                                                ? null
                                                                : servicio.estado_final_equipo ===
                                                                    'APROBADO'
                                                                  ? 'Aprobado'
                                                                  : 'Rechazado'
                                                        }
                                                    />
                                                </Tarjeta>
                                            ),
                                        )}
                                    </Lista>
                                </TabsContent>

                                <TabsContent value="historico" className="pt-2">
                                    <Lista vacio="El equipo no tiene servicios históricos importados.">
                                        {filtrados.historicos.map(
                                            (historico) => (
                                                <Tarjeta
                                                    key={historico.id}
                                                    titulo={`${historico.tipo_servicio === 'MANTENIMIENTO' ? 'Mantenimiento' : 'Calibración'} · ${historico.fecha_servicio}`}
                                                    badge={
                                                        <Badge
                                                            className={
                                                                historico.es_tercero
                                                                    ? BADGE_TERCERO
                                                                    : BADGE_PROPIO
                                                            }
                                                        >
                                                            {historico.es_tercero
                                                                ? 'Tercero'
                                                                : 'Propio'}
                                                        </Badge>
                                                    }
                                                    acciones={
                                                        historico.adjunto_url ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        historico.adjunto_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    data-test="historico-adjunto"
                                                                >
                                                                    <Paperclip className="h-4 w-4" />{' '}
                                                                    Adjunto
                                                                </a>
                                                            </Button>
                                                        ) : null
                                                    }
                                                >
                                                    {historico.es_tercero ? (
                                                        <Dato
                                                            label="Empresa tercera"
                                                            value={
                                                                historico.empresa_tercero
                                                            }
                                                        />
                                                    ) : (
                                                        <Dato
                                                            label="Laboratorio"
                                                            value={
                                                                datos?.laboratorioPropio ??
                                                                'Laboratorio propio'
                                                            }
                                                        />
                                                    )}
                                                    <Dato
                                                        label="Archivo"
                                                        value={
                                                            historico.adjunto_nombre
                                                        }
                                                    />
                                                </Tarjeta>
                                            ),
                                        )}
                                    </Lista>
                                </TabsContent>
                            </Tabs>
                        </div>
                    )}

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cerrar
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <VerIngresoModal
                ingreso={detalleDe('ingreso', datos?.ingresos)}
                novedadesIngreso={datos?.novedadesIngreso ?? []}
                open={abiertoSi('ingreso')}
                onOpenChange={setDetalleOpen}
            />
            <VerMantenimientoModal
                mantenimiento={detalleDe(
                    'mantenimiento',
                    datos?.mantenimientos,
                )}
                open={abiertoSi('mantenimiento')}
                onOpenChange={setDetalleOpen}
            />
            <VerCalibracionModal
                calibracion={detalleDe('calibracion', datos?.calibraciones)}
                open={abiertoSi('calibracion')}
                onOpenChange={setDetalleOpen}
            />
            <VerDespachoModal
                despacho={detalleDe('despacho', datos?.despachos)}
                open={abiertoSi('despacho')}
                onOpenChange={setDetalleOpen}
            />
            <VerServicioTerceroModal
                servicio={detalleDe(
                    'servicio_tercero',
                    datos?.servicios_terceros,
                )}
                open={abiertoSi('servicio_tercero')}
                onOpenChange={setDetalleOpen}
            />
            {equipo ? (
                <AgregarHistoricoModal
                    equipo={equipo}
                    empresasTerceras={datos?.empresasTerceras ?? []}
                    open={agregarOpen}
                    onOpenChange={setAgregarOpen}
                    onAgregado={(historico) => {
                        // Se inserta en su lugar (del más reciente al más antiguo) y se muestra.
                        setDatos((actuales) =>
                            actuales
                                ? {
                                      ...actuales,
                                      historicos: [
                                          ...actuales.historicos,
                                          historico,
                                      ].sort((a, b) =>
                                          b.fecha_servicio.localeCompare(
                                              a.fecha_servicio,
                                          ),
                                      ),
                                  }
                                : actuales,
                        );
                        setPestana('historico');
                    }}
                />
            ) : null}
        </>
    );
}
