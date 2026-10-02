import { useHttp } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AgregarEquipoCorrectivo,
    type CorrectivoPendiente,
    CorrectivoPendienteItem,
} from '@/components/ingresos/agregar-equipo-correctivo';
import {
    estadosVencimiento,
    tiposServicio,
} from '@/components/equipos/equipo-programacion-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { usePermissions } from '@/hooks/use-permissions';
import { buscar } from '@/routes/equipo-programaciones';
import type {
    EquipoProgramacionBusquedaItem,
    EstadoVencimiento,
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

type ResultadoProps = {
    item: EquipoProgramacionBusquedaItem;
    puedeEditar: boolean;
    errors: Partial<Record<string, string>>;
};

/**
 * Una fila de equipo (preventivo encontrado por el buscador, o correctivo ya agendado).
 * Para los preventivos, "agendar" se edita localmente y escribe un input oculto
 * (programaciones_actualizadas[id][agendar]) que viaja junto con el resto del
 * formulario del ingreso cuando se pulsa "Guardar ingreso" / "Guardar cambios". No
 * hace ninguna petición propia: todo se guarda en una sola petición, junto al ingreso.
 */
function ProgramacionResultado({ item, puedeEditar, errors }: ResultadoProps) {
    const [agendar, setAgendar] = useState(item.agendar);

    const esPreventivo = item.tipo_mantenimiento === 'PREVENTIVO';
    const prefijo = `programaciones_actualizadas.${item.id}`;

    return (
        <li
            className="space-y-3 px-3 py-2"
            data-test="programacion-busqueda-item"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-2">
                    {puedeEditar && esPreventivo ? (
                        <Checkbox
                            checked={agendar}
                            onCheckedChange={(checked) =>
                                setAgendar(checked === true)
                            }
                            aria-label="Agendar este equipo"
                            className="mt-1"
                            data-test="agendar-checkbox"
                        />
                    ) : null}

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
                </div>

                {item.fecha_proximo_servicio ? (
                    <Badge
                        className={
                            colorBadgeVencimiento[item.estado_vencimiento]
                        }
                    >
                        {estadoVencimientoLabel(item.estado_vencimiento)}
                    </Badge>
                ) : null}
            </div>

            {puedeEditar && esPreventivo ? (
                <>
                    <input
                        type="hidden"
                        name={`programaciones_actualizadas[${item.id}][agendar]`}
                        value={agendar ? '1' : '0'}
                    />
                    <InputError message={errors[`${prefijo}.agendar`]} />
                </>
            ) : null}
        </li>
    );
}

/**
 * Vista previa de los equipos cuya próxima programación de servicio cae dentro del
 * Desde/Hasta/Bahía que se lleven en el formulario del ingreso (o ya enlazados a este
 * ingreso, al editar). Cada fila de un preventivo permite marcar si se agenda o no, y
 * se puede agregar equipos correctivos aparte; nada de esto llama al servidor por su
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
                                campo="programaciones_correctivas"
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
