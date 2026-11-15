import { Form, Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    Clock,
    Users,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import RecordatorioController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioController';
import AccionesPrueba from '@/components/recordatorios/acciones-prueba';
import AsignarUsuariosBahiasModal from '@/components/bahias/asignar-usuarios-bahias-modal';
import { StatTile } from '@/components/dashboard/indicador-comun';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ContenidoCorreo from '@/components/recordatorios/contenido-correo';
import HistorialEnvios from '@/components/recordatorios/historial-envios';
import CorreosCopia from '@/components/recordatorios/correos-copia';
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
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { show } from '@/routes/recordatorio';
import type {
    Bahia,
    BahiaUsuarioOption,
    ClienteRecordatorio,
    EnviosRecordatorioPaginator,
    FrecuenciaRecordatorio,
    MesAvisoRecordatorio,
    PlantillaRecordatorio,
    PlantillasCorreo,
    Recordatorio,
    ResumenRecordatorio,
    TipoPlantillaRecordatorio,
} from '@/types';

type Props = {
    recordatorio: Recordatorio;
    resumen: ResumenRecordatorio;
    /** Los clientes que recibirían correo si se enviara hoy. */
    clientes_recordatorio: ClienteRecordatorio[];
    /** Las plantillas de cada correo y cuál está en uso. */
    plantillas: Record<TipoPlantillaRecordatorio, PlantillasCorreo>;
    /** Historial de correos enviados, paginado. */
    envios: EnviosRecordatorioPaginator;
    plantillas_por_defecto: Record<
        TipoPlantillaRecordatorio,
        PlantillaRecordatorio
    >;
    /** Variable (ej. "{nombre}") => lo que significa. */
    variables_plantilla: Record<string, string>;
    /** Solo si el usuario puede asignar usuarios a bahías. */
    asignacion_bahias: {
        bahias: Bahia[];
        usuarios: BahiaUsuarioOption[];
    } | null;
};

const DIAS_SEMANA = [
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
    'Domingo',
];

/** "13:00" en UTC como hora local del navegador, ej. "08:00 a. m.". */
function horaLocal(horaUtc: string): string | null {
    const [horas, minutos] = horaUtc.split(':').map(Number);

    if (Number.isNaN(horas) || Number.isNaN(minutos)) {
        return null;
    }

    return new Date(Date.UTC(2000, 0, 3, horas, minutos)).toLocaleTimeString(
        'es',
        { hour: '2-digit', minute: '2-digit' },
    );
}

/** "2026-11-30" como fecha local (sin desfase de zona horaria). */
function fechaLocal(fecha: string): Date {
    const [anio, mes, dia] = fecha.split('-').map(Number);

    return new Date(anio, mes - 1, dia);
}

function fechaCorta(fecha: Date): string {
    return fecha.toLocaleDateString('es', { day: 'numeric', month: 'long' });
}

/**
 * El próximo envío mensual (el día elegido de este mes, o del siguiente si ya pasó) y el
 * rango de vencimientos que avisa: del envío a fin de mes, o todo el mes siguiente.
 */
function proximoEnvioMensual(
    diaMes: number,
    mesAviso: MesAvisoRecordatorio,
): { envio: Date; desde: Date; hasta: Date } {
    const hoy = new Date();
    const yaPaso = hoy.getDate() > diaMes;
    const envio = new Date(
        hoy.getFullYear(),
        hoy.getMonth() + (yaPaso ? 1 : 0),
        diaMes,
    );
    // El día 0 del mes que sigue al avisado es el último día del avisado.
    const hasta = new Date(
        envio.getFullYear(),
        envio.getMonth() + (mesAviso === 'SIGUIENTE' ? 2 : 1),
        0,
    );
    const desde =
        mesAviso === 'SIGUIENTE'
            ? new Date(envio.getFullYear(), envio.getMonth() + 1, 1)
            : envio;

    return { envio, desde, hasta };
}

function Seccion({
    titulo,
    descripcion,
    children,
}: {
    titulo: string;
    descripcion: string;
    children: ReactNode;
}) {
    return (
        <section className="grid h-fit gap-4 rounded-lg border p-6">
            <div>
                <h2 className="text-sm font-semibold">{titulo}</h2>
                <p className="text-sm text-muted-foreground">{descripcion}</p>
            </div>
            {children}
        </section>
    );
}

export default function RecordatorioShow({
    recordatorio,
    resumen,
    clientes_recordatorio,
    plantillas,
    envios,
    plantillas_por_defecto,
    variables_plantilla,
    asignacion_bahias,
}: Props) {
    const { can } = usePermissions();
    const puedeEditar = can('recordatorios.editar');
    const [activo, setActivo] = useState(recordatorio.activo);
    const [frecuencia, setFrecuencia] = useState<FrecuenciaRecordatorio>(
        recordatorio.frecuencia,
    );
    const [diaMes, setDiaMes] = useState(recordatorio.dia_mes ?? 1);
    const [mesAviso, setMesAviso] = useState<MesAvisoRecordatorio>(
        recordatorio.mes_aviso,
    );
    const proximoMensual = proximoEnvioMensual(diaMes, mesAviso);
    const [horaEnvio, setHoraEnvio] = useState(recordatorio.hora_envio);
    const local = horaLocal(horaEnvio);
    const ultimoEnvio = recordatorio.ultimo_envio_at
        ? new Date(recordatorio.ultimo_envio_at)
        : null;

    return (
        <>
            <Head title="Recordatorios" />

            <h1 className="sr-only">Recordatorios</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Recordatorios"
                        description="Envía por correo un PDF con los equipos vencidos, próximos a vencer y agendados"
                    />
                </div>

                <section className="grid gap-2">
                    <p className="text-xs text-muted-foreground">
                        Si se enviara hoy, con la configuración guardada
                        (vencidos y lo que vence entre el{' '}
                        {fechaCorta(fechaLocal(resumen.avisa_desde))} y el{' '}
                        {fechaCorta(fechaLocal(resumen.avisa_hasta))}):
                    </p>
                    <div className="grid auto-rows-min grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                        <StatTile
                            icono={AlertTriangle}
                            valor={String(resumen.vencidos)}
                            etiqueta="Equipos vencidos"
                            tono="red"
                            testId="recordatorio-resumen-vencidos"
                        />
                        <StatTile
                            icono={CalendarClock}
                            valor={String(resumen.proximos_a_vencer)}
                            etiqueta="Próximos a vencer"
                            tono="amber"
                            testId="recordatorio-resumen-proximos"
                        />
                        <StatTile
                            icono={CalendarCheck}
                            valor={String(resumen.agendados)}
                            etiqueta="Agendados"
                            tono="sky"
                            testId="recordatorio-resumen-agendados"
                        />
                        <StatTile
                            icono={UsersRound}
                            valor={String(resumen.clientes)}
                            etiqueta="Clientes"
                            detalle="Reciben su correo"
                            tono="emerald"
                            testId="recordatorio-resumen-clientes"
                        />
                        <StatTile
                            icono={Users}
                            valor={String(resumen.usuarios_bahia)}
                            etiqueta="Usuarios de bahía"
                            detalle="Reciben el resumen de sus bahías"
                            tono="violet"
                            testId="recordatorio-resumen-usuarios"
                        />
                        <StatTile
                            icono={Clock}
                            valor={
                                ultimoEnvio
                                    ? ultimoEnvio.toLocaleDateString('es', {
                                          day: '2-digit',
                                          month: 'short',
                                      })
                                    : 'Nunca'
                            }
                            detalle={
                                ultimoEnvio
                                    ? ultimoEnvio.toLocaleTimeString('es', {
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : 'Aún no se ha enviado'
                            }
                            etiqueta="Último envío"
                            tono="sky"
                            testId="recordatorio-resumen-ultimo-envio"
                        />
                    </div>
                </section>

                <Tabs defaultValue="configuracion">
                    <TabsList>
                        <TabsTrigger
                            value="configuracion"
                            data-test="recordatorio-tab-configuracion"
                        >
                            Configuración
                        </TabsTrigger>
                        <TabsTrigger
                            value="contenido"
                            data-test="recordatorio-tab-contenido"
                        >
                            Contenido del correo
                        </TabsTrigger>
                        <TabsTrigger
                            value="historial"
                            data-test="recordatorio-tab-historial"
                        >
                            Historial de envíos
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="configuracion"
                        forceMount
                        className="data-[state=inactive]:hidden"
                    >
                        <Form
                            {...RecordatorioController.update.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <fieldset
                                        disabled={!puedeEditar}
                                        className="grid gap-6 xl:grid-cols-2"
                                    >
                                        <div className="grid h-fit gap-6">
                                            <Seccion
                                                titulo="Envío automático"
                                                descripcion="Mientras esté activo, el correo sale solo en el día y la hora configurados."
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Switch
                                                        id="activo"
                                                        checked={activo}
                                                        onCheckedChange={
                                                            setActivo
                                                        }
                                                        disabled={!puedeEditar}
                                                        data-test="recordatorio-activo"
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="activo"
                                                        value={
                                                            activo ? '1' : '0'
                                                        }
                                                    />
                                                    <Label htmlFor="activo">
                                                        {activo
                                                            ? 'Recordatorio activo'
                                                            : 'Recordatorio inactivo'}
                                                    </Label>
                                                </div>
                                                <InputError
                                                    message={errors.activo}
                                                />
                                            </Seccion>

                                            <Seccion
                                                titulo="Frecuencia de envío"
                                                descripcion="Una vez a la semana o una vez al mes, el día elegido."
                                            >
                                                <div className="grid gap-6 sm:grid-cols-3">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="frecuencia">
                                                            Frecuencia
                                                        </Label>
                                                        <Select
                                                            name="frecuencia"
                                                            value={frecuencia}
                                                            onValueChange={(
                                                                valor,
                                                            ) =>
                                                                setFrecuencia(
                                                                    valor as FrecuenciaRecordatorio,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                id="frecuencia"
                                                                className="w-full"
                                                            >
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="SEMANAL">
                                                                    Semanal
                                                                </SelectItem>
                                                                <SelectItem value="MENSUAL">
                                                                    Mensual
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        <InputError
                                                            message={
                                                                errors.frecuencia
                                                            }
                                                        />
                                                    </div>

                                                    {frecuencia ===
                                                    'SEMANAL' ? (
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="dia_semana">
                                                                Día de la semana
                                                            </Label>
                                                            <Select
                                                                name="dia_semana"
                                                                defaultValue={String(
                                                                    recordatorio.dia_semana ??
                                                                        1,
                                                                )}
                                                            >
                                                                <SelectTrigger
                                                                    id="dia_semana"
                                                                    className="w-full"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {DIAS_SEMANA.map(
                                                                        (
                                                                            dia,
                                                                            indice,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    dia
                                                                                }
                                                                                value={String(
                                                                                    indice +
                                                                                        1,
                                                                                )}
                                                                            >
                                                                                {
                                                                                    dia
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <InputError
                                                                message={
                                                                    errors.dia_semana
                                                                }
                                                            />
                                                        </div>
                                                    ) : (
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="dia_mes">
                                                                Día del mes
                                                            </Label>
                                                            <Select
                                                                name="dia_mes"
                                                                value={String(
                                                                    diaMes,
                                                                )}
                                                                onValueChange={(
                                                                    valor,
                                                                ) =>
                                                                    setDiaMes(
                                                                        Number(
                                                                            valor,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id="dia_mes"
                                                                    className="w-full"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {Array.from(
                                                                        {
                                                                            length: 28,
                                                                        },
                                                                        (
                                                                            _,
                                                                            indice,
                                                                        ) =>
                                                                            indice +
                                                                            1,
                                                                    ).map(
                                                                        (
                                                                            dia,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    dia
                                                                                }
                                                                                value={String(
                                                                                    dia,
                                                                                )}
                                                                            >
                                                                                {
                                                                                    dia
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <InputError
                                                                message={
                                                                    errors.dia_mes
                                                                }
                                                            />
                                                        </div>
                                                    )}

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="hora_envio">
                                                            Hora de envío (UTC)
                                                        </Label>
                                                        <Input
                                                            id="hora_envio"
                                                            name="hora_envio"
                                                            type="time"
                                                            step={60}
                                                            value={horaEnvio}
                                                            onChange={(
                                                                evento,
                                                            ) =>
                                                                setHoraEnvio(
                                                                    evento
                                                                        .target
                                                                        .value,
                                                                )
                                                            }
                                                            required
                                                        />
                                                        {local ? (
                                                            <p className="text-xs text-muted-foreground">
                                                                Equivale a las{' '}
                                                                {local} en tu
                                                                hora local.
                                                            </p>
                                                        ) : null}
                                                        <InputError
                                                            message={
                                                                errors.hora_envio
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                {frecuencia === 'SEMANAL' ? (
                                                    <div className="grid max-w-xs gap-2">
                                                        <Label htmlFor="dias_anticipacion">
                                                            Avisar los que
                                                            vencen en los
                                                            próximos (días)
                                                        </Label>
                                                        <Input
                                                            id="dias_anticipacion"
                                                            name="dias_anticipacion"
                                                            type="number"
                                                            min={1}
                                                            max={365}
                                                            defaultValue={
                                                                recordatorio.dias_anticipacion
                                                            }
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.dias_anticipacion
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="mes_aviso">
                                                            Mes a avisar
                                                        </Label>
                                                        <Select
                                                            name="mes_aviso"
                                                            value={mesAviso}
                                                            onValueChange={(
                                                                valor,
                                                            ) =>
                                                                setMesAviso(
                                                                    valor as MesAvisoRecordatorio,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                id="mes_aviso"
                                                                className="w-full sm:max-w-xs"
                                                                data-test="recordatorio-mes-aviso"
                                                            >
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="ACTUAL">
                                                                    El mes en
                                                                    que se envía
                                                                </SelectItem>
                                                                <SelectItem value="SIGUIENTE">
                                                                    El mes
                                                                    siguiente
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        <p className="text-xs text-muted-foreground">
                                                            El correo se enviará
                                                            el{' '}
                                                            {fechaCorta(
                                                                proximoMensual.envio,
                                                            )}{' '}
                                                            e incluirá los
                                                            equipos vencidos y
                                                            los que vencen entre
                                                            el{' '}
                                                            <span className="font-medium text-foreground">
                                                                {fechaCorta(
                                                                    proximoMensual.desde,
                                                                )}
                                                            </span>{' '}
                                                            y el{' '}
                                                            <span className="font-medium text-foreground">
                                                                {fechaCorta(
                                                                    proximoMensual.hasta,
                                                                )}
                                                            </span>
                                                            .
                                                        </p>
                                                        <InputError
                                                            message={
                                                                errors.mes_aviso
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </Seccion>
                                        </div>

                                        <Seccion
                                            titulo="Destinatarios"
                                            descripcion="Cada destinatario recibe solo los equipos que le corresponden. Si no tiene ninguno que avisar, no se le envía correo."
                                        >
                                            <div className="grid gap-1">
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="enviar_a_clientes"
                                                        name="enviar_a_clientes"
                                                        defaultChecked={
                                                            recordatorio.enviar_a_clientes
                                                        }
                                                    />
                                                    <Label htmlFor="enviar_a_clientes">
                                                        Enviar a cada cliente
                                                        sus equipos
                                                    </Label>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Se envía al correo
                                                    registrado del cliente, con
                                                    los equipos de los que es
                                                    dueño.
                                                </p>
                                                <AccionesPrueba
                                                    tipo="cliente"
                                                    puedeEnviar={puedeEditar}
                                                    clientes={
                                                        clientes_recordatorio
                                                    }
                                                />
                                            </div>

                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="grid gap-1">
                                                    <div className="flex items-center gap-3">
                                                        <Checkbox
                                                            id="enviar_resumen_bahia"
                                                            name="enviar_resumen_bahia"
                                                            defaultChecked={
                                                                recordatorio.enviar_resumen_bahia
                                                            }
                                                        />
                                                        <Label htmlFor="enviar_resumen_bahia">
                                                            Resumen por bahía
                                                        </Label>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        Cada usuario asignado a
                                                        bahías recibe los
                                                        equipos de sus bahías,
                                                        agrupados por bahía.
                                                    </p>
                                                    <AccionesPrueba
                                                        tipo="bahia"
                                                        puedeEnviar={
                                                            puedeEditar
                                                        }
                                                    />
                                                </div>

                                                {asignacion_bahias ? (
                                                    <AsignarUsuariosBahiasModal
                                                        bahias={
                                                            asignacion_bahias.bahias
                                                        }
                                                        usuarios={
                                                            asignacion_bahias.usuarios
                                                        }
                                                        modoInicial="porUsuario"
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            data-test="recordatorio-asignar-bahias"
                                                        >
                                                            <Users /> Asignar
                                                            bahías a usuarios
                                                        </Button>
                                                    </AsignarUsuariosBahiasModal>
                                                ) : null}
                                            </div>

                                            <CorreosCopia
                                                iniciales={
                                                    recordatorio.correos_copia
                                                }
                                                errores={errors}
                                            />
                                        </Seccion>
                                    </fieldset>

                                    {puedeEditar ? (
                                        <div className="mt-4 flex justify-end">
                                            <Button
                                                type="submit"
                                                loading={processing}
                                                data-test="recordatorio-guardar"
                                            >
                                                Guardar configuración
                                            </Button>
                                        </div>
                                    ) : null}
                                </>
                            )}
                        </Form>
                    </TabsContent>

                    {/* Fuera del formulario de configuración: cada plantilla se guarda aparte. */}
                    <TabsContent
                        value="contenido"
                        forceMount
                        className="data-[state=inactive]:hidden"
                    >
                        <ContenidoCorreo
                            plantillas={plantillas}
                            porDefecto={plantillas_por_defecto}
                            variables={variables_plantilla}
                            editable={puedeEditar}
                        />
                    </TabsContent>

                    <TabsContent value="historial">
                        <HistorialEnvios envios={envios} />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

RecordatorioShow.layout = {
    breadcrumbs: [
        {
            title: 'Recordatorios',
            href: show(),
        },
    ],
};
