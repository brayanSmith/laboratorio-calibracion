import { useHttp } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Combobox from '@/components/combobox';
import {
    estadosProgramacion,
    estadosVencimiento,
    motivosNoIngreso,
    tiposServicio,
} from '@/components/equipos/equipo-programacion-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import TogglePerilla3 from '@/components/toggle-perilla-3';
import { usePermissions } from '@/hooks/use-permissions';
import {
    buscar,
    equiposDisponibles as equiposDisponiblesRoute,
} from '@/routes/equipo-programaciones';
import type {
    EquipoDisponibleItem,
    EquipoProgramacionBusquedaItem,
    EstadoProgramacion,
    EstadoVencimiento,
    MotivoNoIngreso,
} from '@/types';

type Props = {
    desde: string;
    hasta: string;
    bahiaId: string | undefined;
    /** Equipos ya enlazados a este ingreso, solo al editar. */
    resultadosIniciales?: EquipoProgramacionBusquedaItem[];
    errors: Partial<Record<string, string>>;
};

/** El valor guardado es un CSV de uno o varios tipos, ej: "MANTENIMIENTO,CALIBRACION". */
function tiposServicioLabel(value: string): string {
    return value
        .split(',')
        .map(
            (tipo) =>
                tiposServicio.find((option) => option.value === tipo)?.label ??
                tipo,
        )
        .join(' + ');
}

function estadoVencimientoLabel(value: string): string {
    return (
        estadosVencimiento.find((option) => option.value === value)?.label ??
        value
    );
}

/** Al día en verde, próximo a vencer en amarillo, vencido en rojo. */
const colorBadgeVencimiento: Record<EstadoVencimiento, string> = {
    AL_DIA: '!border-transparent !bg-emerald-500 !text-white',
    PROXIMO_A_VENCER: '!border-transparent !bg-amber-500 !text-white',
    VENCIDO: '!border-transparent !bg-red-500 !text-white',
};

/** Agendado en verde, pendiente en amarillo, cancelado en rojo. */
const colorEstadoProgramacion: Record<EstadoProgramacion, string> = {
    PENDIENTE: 'bg-amber-500',
    AGENDADO: 'bg-emerald-500',
    CANCELADO: 'bg-red-500',
};

/** Agendado en verde, pendiente en amarillo, cancelado en rojo. */
const colorBadgeEstadoProgramacion: Record<EstadoProgramacion, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    AGENDADO: '!border-transparent !bg-emerald-500 !text-white',
    CANCELADO: '!border-transparent !bg-red-500 !text-white',
};

const colorTextoEstadoProgramacion: Record<EstadoProgramacion, string> = {
    PENDIENTE: 'text-amber-700 dark:text-amber-400',
    AGENDADO: 'text-emerald-700 dark:text-emerald-400',
    CANCELADO: 'text-red-700 dark:text-red-400',
};

/** Tiñe toda la tarjeta: agendado en verde tenue, cancelado en rojo tenue. */
const fondoTenueEstadoProgramacion: Record<EstadoProgramacion, string> = {
    PENDIENTE: '',
    AGENDADO: 'bg-emerald-50 dark:bg-emerald-950/20',
    CANCELADO: 'bg-red-50 dark:bg-red-950/20',
};

type ResultadoProps = {
    item: EquipoProgramacionBusquedaItem;
    puedeEditar: boolean;
    errors: Partial<Record<string, string>>;
};

/**
 * Una fila de equipo (preventivo encontrado por el buscador, o correctivo ya agendado).
 * El estado se edita localmente; mientras haya cambios sin guardar, la fila escribe
 * inputs ocultos (programaciones_actualizadas[id][...]) que viajan junto con el resto
 * del formulario del ingreso cuando se pulsa "Guardar ingreso" / "Guardar cambios". No
 * hace ninguna petición propia: todo se guarda en una sola petición, junto al ingreso.
 */
function ProgramacionResultado({ item, puedeEditar, errors }: ResultadoProps) {
    const [estado, setEstado] = useState<EstadoProgramacion>(
        item.estado_programacion,
    );
    const [motivo, setMotivo] = useState<MotivoNoIngreso | ''>(
        item.motivo_no_ingreso ?? '',
    );
    const [observacion, setObservacion] = useState(
        item.observacion_no_ingreso ?? '',
    );
    const [reAgendar, setReAgendar] = useState(item.re_agendar);
    const [fechaAgendamiento, setFechaAgendamiento] = useState(
        item.datos_re_agendamiento?.fecha_proximo_agendamiento ?? '',
    );
    // Si ya llegó cancelado (de una búsqueda anterior), arranca compacto; si recién
    // se cancela en esta sesión, arranca expandido para poder llenar los datos.
    const [expandido, setExpandido] = useState(
        item.estado_programacion !== 'CANCELADO',
    );

    const cancelado = estado === 'CANCELADO';
    const esOtroMotivo = motivo === 'OTRO';
    const motivoLabel = motivosNoIngreso.find(
        (option) => option.value === motivo,
    )?.label;
    const resumenCancelacion = [
        motivoLabel ?? 'Sin motivo seleccionado',
        esOtroMotivo && observacion ? observacion : null,
        reAgendar
            ? fechaAgendamiento
                ? `Re-agenda: ${fechaAgendamiento}`
                : 'Por re-agendar'
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
    const cambios =
        estado !== item.estado_programacion ||
        (cancelado &&
            (motivo !== (item.motivo_no_ingreso ?? '') ||
                (esOtroMotivo &&
                    observacion !== (item.observacion_no_ingreso ?? '')) ||
                reAgendar !== item.re_agendar ||
                (reAgendar &&
                    fechaAgendamiento !==
                        (item.datos_re_agendamiento
                            ?.fecha_proximo_agendamiento ?? ''))));

    const prefijo = `programaciones_actualizadas.${item.id}`;

    return (
        <li
            className={`space-y-3 px-3 py-2 ${fondoTenueEstadoProgramacion[estado]}`}
            data-test="programacion-busqueda-item"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <span className="text-sm font-medium">
                        {item.equipo.codigo} · {item.equipo.modelo}
                    </span>
                    <p className="text-xs text-muted-foreground">
                        {tiposServicioLabel(item.tipo_servicio)}
                        {item.equipo.cliente
                            ? ` · ${item.equipo.cliente.nombre}`
                            : ''}
                        {item.fecha_proximo_servicio
                            ? ` · Próximo servicio: ${item.fecha_proximo_servicio.slice(0, 10)}`
                            : item.falla_detectada
                              ? ` · Falla: ${item.falla_detectada}`
                              : ''}
                    </p>
                </div>

                {/* El estado de vencimiento y el control de estado van juntos, a la
                    derecha, para no alargar la tarjeta con un bloque aparte abajo. */}
                <div className="flex flex-col items-end gap-1">
                    {item.fecha_proximo_servicio ? (
                        <Badge
                            className={
                                colorBadgeVencimiento[item.estado_vencimiento]
                            }
                        >
                            {estadoVencimientoLabel(item.estado_vencimiento)}
                        </Badge>
                    ) : null}

                    {puedeEditar ? (
                        <>
                            <span
                                className={`text-xs font-medium ${colorTextoEstadoProgramacion[estado]}`}
                            >
                                {
                                    estadosProgramacion.find(
                                        (option) => option.value === estado,
                                    )?.label
                                }
                            </span>

                            <TogglePerilla3
                                value={estado}
                                opciones={estadosProgramacion}
                                colorPerilla={colorEstadoProgramacion}
                                ariaLabel="Estado de la programación"
                                onSeleccionar={(valor) => {
                                    const nuevoEstado =
                                        valor as EstadoProgramacion;

                                    setEstado(nuevoEstado);

                                    // Al pasar recién a Cancelado, se abre para
                                    // pedir el motivo; de resto no se toca.
                                    if (
                                        nuevoEstado === 'CANCELADO' &&
                                        estado !== 'CANCELADO'
                                    ) {
                                        setExpandido(true);
                                    }
                                }}
                            />

                            <InputError
                                message={
                                    errors[`${prefijo}.estado_programacion`]
                                }
                            />
                        </>
                    ) : null}
                </div>
            </div>

            {puedeEditar && cancelado && !expandido ? (
                <button
                    type="button"
                    onClick={() => setExpandido(true)}
                    className="flex w-full items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1.5 text-left"
                    data-test="cancelacion-resumen"
                >
                    <p className="truncate text-xs text-muted-foreground">
                        {resumenCancelacion}
                    </p>
                    <ChevronDown className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                </button>
            ) : null}

            {puedeEditar && cancelado && expandido ? (
                <div className="space-y-3 rounded-md bg-muted/40 p-2">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">
                            Detalle de la cancelación
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6"
                            onClick={() => setExpandido(false)}
                            aria-label="Compactar detalle de cancelación"
                            data-test="cancelacion-comprimir"
                        >
                            <ChevronUp className="h-3.5 w-3.5" />
                        </Button>
                    </div>

                    <div className="grid gap-2">
                        <Label
                            htmlFor={`motivo-no-ingreso-${item.id}`}
                            className="text-xs"
                        >
                            Motivo de no ingreso
                        </Label>
                        <Select
                            value={motivo}
                            onValueChange={(value) =>
                                setMotivo(value as MotivoNoIngreso)
                            }
                        >
                            <SelectTrigger
                                id={`motivo-no-ingreso-${item.id}`}
                                size="sm"
                                className="w-full"
                            >
                                <SelectValue placeholder="Selecciona un motivo" />
                            </SelectTrigger>
                            <SelectContent>
                                {motivosNoIngreso.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors[`${prefijo}.motivo_no_ingreso`]}
                        />
                    </div>

                    {esOtroMotivo ? (
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`observacion-no-ingreso-${item.id}`}
                                className="text-xs"
                            >
                                Observación
                            </Label>
                            <Textarea
                                id={`observacion-no-ingreso-${item.id}`}
                                value={observacion}
                                onChange={(event) =>
                                    setObservacion(event.target.value)
                                }
                                placeholder="Detalla el motivo de la cancelación"
                                rows={2}
                            />
                            <InputError
                                message={
                                    errors[`${prefijo}.observacion_no_ingreso`]
                                }
                            />
                        </div>
                    ) : null}

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={`re-agendar-${item.id}`}
                            checked={reAgendar}
                            onCheckedChange={(checked) =>
                                setReAgendar(checked === true)
                            }
                        />
                        <Label
                            htmlFor={`re-agendar-${item.id}`}
                            className="text-xs font-normal"
                        >
                            Re-agendar
                        </Label>
                    </div>

                    {reAgendar ? (
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`fecha-agendamiento-${item.id}`}
                                className="text-xs"
                            >
                                Fecha del próximo agendamiento
                            </Label>
                            <Input
                                id={`fecha-agendamiento-${item.id}`}
                                type="date"
                                value={fechaAgendamiento}
                                onChange={(event) =>
                                    setFechaAgendamiento(event.target.value)
                                }
                                className="w-48"
                            />
                            <InputError
                                message={
                                    errors[
                                        `${prefijo}.datos_re_agendamiento.fecha_proximo_agendamiento`
                                    ]
                                }
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {cambios ? (
                <>
                    <input
                        type="hidden"
                        name={`programaciones_actualizadas[${item.id}][estado_programacion]`}
                        value={estado}
                    />
                    <input
                        type="hidden"
                        name={`programaciones_actualizadas[${item.id}][motivo_no_ingreso]`}
                        value={cancelado ? motivo : ''}
                    />
                    <input
                        type="hidden"
                        name={`programaciones_actualizadas[${item.id}][observacion_no_ingreso]`}
                        value={cancelado && esOtroMotivo ? observacion : ''}
                    />
                    <input
                        type="hidden"
                        name={`programaciones_actualizadas[${item.id}][re_agendar]`}
                        value={cancelado && reAgendar ? '1' : '0'}
                    />
                    {cancelado && reAgendar ? (
                        <input
                            type="hidden"
                            name={`programaciones_actualizadas[${item.id}][datos_re_agendamiento][fecha_proximo_agendamiento]`}
                            value={fechaAgendamiento}
                        />
                    ) : null}
                </>
            ) : null}
        </li>
    );
}

type CorrectivoPendiente = {
    tempId: string;
    equipoId: string;
    equipoLabel: string;
    fallaDetectada: string;
};

type AgregarEquipoCorrectivoProps = {
    bahiaId: string;
    onAgregar: (pendiente: Omit<CorrectivoPendiente, 'tempId'>) => void;
    onCancelar: () => void;
};

/**
 * Formulario para anotar, localmente, un equipo que falló "de la nada" (no tenía
 * servicio programado) en la bahía del ingreso. No llama al servidor: solo junta el
 * equipo y la falla detectada en la lista de pendientes, que se guarda junto con el
 * resto del ingreso al pulsar "Guardar ingreso" / "Guardar cambios".
 */
function AgregarEquipoCorrectivo({
    bahiaId,
    onAgregar,
    onCancelar,
}: AgregarEquipoCorrectivoProps) {
    const [equipoId, setEquipoId] = useState('');
    const [fallaDetectada, setFallaDetectada] = useState('');
    const { get: cargarEquipos, response: equipos } = useHttp<
        { bahia_id: string },
        EquipoDisponibleItem[]
    >();

    useEffect(() => {
        void cargarEquipos(
            equiposDisponiblesRoute({ query: { bahia_id: bahiaId } }).url,
        );
        // Solo se vuelve a cargar si cambia la bahía del ingreso.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bahiaId]);

    const equipoSeleccionado = (equipos ?? []).find(
        (equipo) => equipo.id.toString() === equipoId,
    );

    const handleAgregar = () => {
        if (!equipoSeleccionado || !fallaDetectada) {
            return;
        }

        onAgregar({
            equipoId,
            equipoLabel: `${equipoSeleccionado.codigo} · ${equipoSeleccionado.modelo}`,
            fallaDetectada,
        });
        setEquipoId('');
        setFallaDetectada('');
    };

    return (
        <div
            className="space-y-3 rounded-md border bg-muted/30 p-3"
            data-test="agregar-equipo-correctivo-form"
        >
            <div className="grid gap-2">
                <Label htmlFor="equipo-correctivo">Equipo</Label>
                <Combobox
                    id="equipo-correctivo"
                    value={equipoId}
                    onValueChange={setEquipoId}
                    options={(equipos ?? []).map((equipo) => ({
                        id: equipo.id,
                        label: `${equipo.codigo} · ${equipo.modelo}`,
                    }))}
                    placeholder="Selecciona un equipo"
                    searchPlaceholder="Buscar equipo..."
                    emptyMessage="Sin equipos en esta bahía."
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="falla-detectada">Falla detectada</Label>
                <Textarea
                    id="falla-detectada"
                    value={fallaDetectada}
                    onChange={(event) => setFallaDetectada(event.target.value)}
                    placeholder="Describe la falla detectada en el equipo"
                    rows={2}
                />
            </div>

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onCancelar}
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    onClick={handleAgregar}
                    disabled={!equipoId || !fallaDetectada}
                    data-test="agregar-equipo-correctivo-guardar"
                >
                    Agregar
                </Button>
            </div>
        </div>
    );
}

type CorrectivoPendienteItemProps = {
    pendiente: CorrectivoPendiente;
    index: number;
    onQuitar: () => void;
    errors: Partial<Record<string, string>>;
};

/**
 * Una fila "por guardar": un equipo correctivo que el usuario anotó en esta sesión,
 * pero que todavía no existe en el servidor. Escribe sus propios inputs ocultos
 * (programaciones_correctivas[index][...]) para que viajen junto con el ingreso.
 */
function CorrectivoPendienteItem({
    pendiente,
    index,
    onQuitar,
    errors,
}: CorrectivoPendienteItemProps) {
    const prefijo = `programaciones_correctivas.${index}`;

    return (
        <li
            className="flex items-start justify-between gap-3 px-3 py-2"
            data-test="correctivo-pendiente-item"
        >
            <div className="space-y-1">
                <span className="text-sm font-medium">
                    {pendiente.equipoLabel}
                </span>
                <p className="text-xs text-muted-foreground">
                    {pendiente.fallaDetectada}
                </p>
                <InputError
                    message={
                        errors[`${prefijo}.equipo_id`] ??
                        errors[`${prefijo}.falla_detectada`]
                    }
                />
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <Badge className={colorBadgeEstadoProgramacion.AGENDADO}>
                    {
                        estadosProgramacion.find(
                            (option) => option.value === 'AGENDADO',
                        )?.label
                    }
                </Badge>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={onQuitar}
                    aria-label="Quitar equipo"
                    data-test="correctivo-pendiente-quitar"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </Button>
            </div>

            <input
                type="hidden"
                name={`programaciones_correctivas[${index}][equipo_id]`}
                value={pendiente.equipoId}
            />
            <input
                type="hidden"
                name={`programaciones_correctivas[${index}][falla_detectada]`}
                value={pendiente.fallaDetectada}
            />
        </li>
    );
}

/**
 * Vista previa de los equipos cuya próxima programación de servicio cae dentro del
 * Desde/Hasta/Bahía que se lleven en el formulario del ingreso (o ya enlazados a este
 * ingreso, al editar). Cada fila permite cambiar el estado de su programación, y se
 * puede agregar equipos correctivos aparte; nada de esto llama al servidor por su
 * cuenta: todo viaja en la misma petición que el ingreso, al guardar, mediante inputs
 * ocultos (ver ProgramacionResultado y CorrectivoPendienteItem).
 */
export default function EquipoProgramacionesBuscador({
    desde,
    hasta,
    bahiaId,
    resultadosIniciales,
    errors,
}: Props) {
    const { can } = usePermissions();
    const [resultados, setResultados] = useState<
        EquipoProgramacionBusquedaItem[] | null
    >(resultadosIniciales ?? null);
    const [mostrarAgregar, setMostrarAgregar] = useState(false);
    const [correctivosPendientes, setCorrectivosPendientes] = useState<
        CorrectivoPendiente[]
    >([]);
    const {
        get,
        response,
        processing,
        errors: erroresBusqueda,
    } = useHttp<
        { desde: string; hasta: string; bahia_id: string },
        EquipoProgramacionBusquedaItem[]
    >();

    useEffect(() => {
        if (response) {
            // Mezcla con lo que ya había (por ejemplo, lo enlazado al editar) en vez de
            // reemplazarlo, para no perder equipos que la búsqueda actual no trae.
            setResultados((actuales) => {
                const mapa = new Map(
                    (actuales ?? []).map((item) => [item.id, item]),
                );
                response.forEach((item) => mapa.set(item.id, item));

                return Array.from(mapa.values());
            });
        }
    }, [response]);

    const puedeBuscar = desde !== '' && hasta !== '' && !!bahiaId;

    const handleBuscar = () => {
        if (!puedeBuscar) {
            return;
        }

        void get(buscar({ query: { desde, hasta, bahia_id: bahiaId } }).url);
    };

    const equiposProgramados = (resultados ?? []).filter(
        (item) => item.tipo_mantenimiento === 'PREVENTIVO',
    );
    const equiposNoProgramados = (resultados ?? []).filter(
        (item) => item.tipo_mantenimiento === 'CORRECTIVO',
    );

    return (
        <div className="space-y-3 rounded-lg border border-dashed p-3 sm:col-span-2">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-medium">
                        Equipos próximos a vencer
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Busca, con el Desde, Hasta y Bahía de arriba, qué
                        equipos tienen servicio programado en ese rango.
                    </p>
                </div>

                <Button
                    type="button"
                    size="sm"
                    onClick={handleBuscar}
                    disabled={!puedeBuscar || processing}
                    data-test="buscar-programaciones-button"
                >
                    <Search className="h-4 w-4" /> Buscar
                </Button>
            </div>

            {erroresBusqueda.desde ||
            erroresBusqueda.hasta ||
            erroresBusqueda.bahia_id ? (
                <p className="text-sm text-red-600 dark:text-red-400">
                    {erroresBusqueda.desde ??
                        erroresBusqueda.hasta ??
                        erroresBusqueda.bahia_id}
                </p>
            ) : null}

            {resultados !== null ? (
                <div className="space-y-1.5">
                    <p className="text-xs font-medium text-muted-foreground">
                        Equipos programados
                    </p>

                    {equiposProgramados.length > 0 ? (
                        <ul className="divide-y rounded-lg border bg-background">
                            {equiposProgramados.map((item) => (
                                <ProgramacionResultado
                                    key={item.id}
                                    item={item}
                                    puedeEditar={can('equipos.editar')}
                                    errors={errors}
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Ningún equipo de esa bahía tiene servicio programado
                            en ese rango.
                        </p>
                    )}
                </div>
            ) : null}

            {equiposNoProgramados.length > 0 ||
            correctivosPendientes.length > 0 ? (
                <div className="space-y-1.5">
                    <p className="text-xs font-medium text-muted-foreground">
                        Equipos no programados
                    </p>

                    <ul
                        className="divide-y rounded-lg border bg-background"
                        data-test="correctivos-list"
                    >
                        {equiposNoProgramados.map((item) => (
                            <ProgramacionResultado
                                key={item.id}
                                item={item}
                                puedeEditar={can('equipos.editar')}
                                errors={errors}
                            />
                        ))}
                        {correctivosPendientes.map((pendiente, index) => (
                            <CorrectivoPendienteItem
                                key={pendiente.tempId}
                                pendiente={pendiente}
                                index={index}
                                errors={errors}
                                onQuitar={() =>
                                    setCorrectivosPendientes((actuales) =>
                                        actuales.filter(
                                            (item) =>
                                                item.tempId !==
                                                pendiente.tempId,
                                        ),
                                    )
                                }
                            />
                        ))}
                    </ul>
                </div>
            ) : null}

            {can('equipos.editar') && bahiaId ? (
                mostrarAgregar ? (
                    <AgregarEquipoCorrectivo
                        bahiaId={bahiaId}
                        onCancelar={() => setMostrarAgregar(false)}
                        onAgregar={(pendiente) => {
                            setCorrectivosPendientes((actuales) => [
                                ...actuales,
                                {
                                    tempId: crypto.randomUUID(),
                                    ...pendiente,
                                },
                            ]);
                            setMostrarAgregar(false);
                        }}
                    />
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setMostrarAgregar(true)}
                        data-test="agregar-equipo-correctivo-button"
                    >
                        <Plus className="h-4 w-4" /> Agregar otros equipos
                    </Button>
                )
            ) : null}
        </div>
    );
}
