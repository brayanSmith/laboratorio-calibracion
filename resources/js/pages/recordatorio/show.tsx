import { Form, Head } from '@inertiajs/react';
import { FileText, Plus, Send, Users, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import RecordatorioController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioController';
import AsignarUsuariosBahiasModal from '@/components/bahias/asignar-usuarios-bahias-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { usePermissions } from '@/hooks/use-permissions';
import { show } from '@/routes/recordatorio';
import type {
    Bahia,
    BahiaUsuarioOption,
    FrecuenciaRecordatorio,
    Recordatorio,
    ResumenRecordatorio,
} from '@/types';

type Props = {
    recordatorio: Recordatorio;
    resumen: ResumenRecordatorio;
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

function Contador({
    valor,
    etiqueta,
    className,
}: {
    valor: number;
    etiqueta: string;
    className: string;
}) {
    return (
        <div className="rounded-md border p-3">
            <div className={`text-2xl font-semibold ${className}`}>{valor}</div>
            <div className="text-xs text-muted-foreground">{etiqueta}</div>
        </div>
    );
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
        <section className="grid gap-4 rounded-lg border p-6">
            <div>
                <h2 className="text-sm font-semibold">{titulo}</h2>
                <p className="text-sm text-muted-foreground">{descripcion}</p>
            </div>
            {children}
        </section>
    );
}

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Lista de correos "con copia": se escriben y se agregan con Enter, coma o el botón.
 * Cada correo viaja al servidor como correos_copia[].
 */
function CorreosCopia({
    iniciales,
    errores,
}: {
    iniciales: string[];
    errores: Record<string, string>;
}) {
    const [correos, setCorreos] = useState<string[]>(iniciales);
    const [nuevo, setNuevo] = useState('');
    const [error, setError] = useState<string | null>(null);

    const erroresServidor = Object.entries(errores)
        .filter(([campo]) => campo.startsWith('correos_copia'))
        .map(([, mensaje]) => mensaje);

    function agregar() {
        const correo = nuevo.trim().toLowerCase();

        if (correo === '') {
            return;
        }

        if (!CORREO_VALIDO.test(correo)) {
            setError('Escribe un correo válido.');

            return;
        }

        if (correos.includes(correo)) {
            setError('Ese correo ya está en la lista.');

            return;
        }

        setCorreos([...correos, correo]);
        setNuevo('');
        setError(null);
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="nuevo_correo_copia">Con copia a</Label>
            <p className="text-xs text-muted-foreground">
                Correos que no están asignados a una bahía, como un coordinador
                o gerente. Cada uno recibe el resumen de todos los equipos,
                agrupados por bahía.
            </p>

            <div className="flex max-w-md gap-2">
                <Input
                    id="nuevo_correo_copia"
                    // También se envía, por si se guarda sin pulsar "Agregar".
                    name="correos_copia[]"
                    type="email"
                    placeholder="correo@empresa.com"
                    value={nuevo}
                    onChange={(evento) => {
                        setNuevo(evento.target.value);
                        setError(null);
                    }}
                    onKeyDown={(evento) => {
                        if (evento.key === 'Enter' || evento.key === ',') {
                            evento.preventDefault();
                            agregar();
                        }
                    }}
                    data-test="recordatorio-nuevo-correo-copia"
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={agregar}
                    data-test="recordatorio-agregar-correo-copia"
                >
                    <Plus /> Agregar
                </Button>
            </div>
            <InputError message={error ?? erroresServidor[0]} />

            {correos.length > 0 ? (
                <ul className="flex flex-wrap gap-2">
                    {correos.map((correo) => (
                        <li
                            key={correo}
                            className="flex items-center gap-1 rounded-full border bg-muted py-0.5 pr-1 pl-3 text-sm"
                        >
                            {correo}
                            <input
                                type="hidden"
                                name="correos_copia[]"
                                value={correo}
                            />
                            <button
                                type="button"
                                className="rounded-full p-0.5 text-muted-foreground hover:bg-background hover:text-foreground"
                                onClick={() =>
                                    setCorreos(
                                        correos.filter(
                                            (actual) => actual !== correo,
                                        ),
                                    )
                                }
                                aria-label={`Quitar ${correo}`}
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

export default function RecordatorioShow({
    recordatorio,
    resumen,
    asignacion_bahias,
}: Props) {
    const { can } = usePermissions();
    const puedeEditar = can('recordatorios.editar');
    const [activo, setActivo] = useState(recordatorio.activo);
    const [frecuencia, setFrecuencia] = useState<FrecuenciaRecordatorio>(
        recordatorio.frecuencia,
    );
    const [horaEnvio, setHoraEnvio] = useState(recordatorio.hora_envio);
    const local = horaLocal(horaEnvio);

    return (
        <>
            <Head title="Recordatorios" />

            <h1 className="sr-only">Recordatorios</h1>

            <div className="flex max-w-5xl flex-col space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Recordatorios"
                        description="Envía por correo un PDF con los equipos vencidos, próximos a vencer y agendados"
                    />

                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={RecordatorioController.vistaPrevia.url()}
                                target="_blank"
                                rel="noreferrer"
                                data-test="recordatorio-vista-previa"
                            >
                                <FileText /> Vista previa PDF
                            </a>
                        </Button>

                        {puedeEditar ? (
                            <Form
                                {...RecordatorioController.enviarPrueba.form()}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        loading={processing}
                                        data-test="recordatorio-prueba"
                                    >
                                        <Send /> Enviarme una prueba
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
                    <Form
                        {...RecordatorioController.update.form()}
                        className="space-y-6"
                        options={{ preserveScroll: true }}
                    >
                        {({ errors, processing }) => (
                            <fieldset
                                disabled={!puedeEditar}
                                className="space-y-6"
                            >
                                <Seccion
                                    titulo="Envío automático"
                                    descripcion="Mientras esté activo, el correo sale solo en el día y la hora configurados."
                                >
                                    <div className="flex items-center gap-3">
                                        <Switch
                                            id="activo"
                                            checked={activo}
                                            onCheckedChange={setActivo}
                                            disabled={!puedeEditar}
                                            data-test="recordatorio-activo"
                                        />
                                        <input
                                            type="hidden"
                                            name="activo"
                                            value={activo ? '1' : '0'}
                                        />
                                        <Label htmlFor="activo">
                                            {activo
                                                ? 'Recordatorio activo'
                                                : 'Recordatorio inactivo'}
                                        </Label>
                                    </div>
                                    <InputError message={errors.activo} />
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
                                                onValueChange={(valor) =>
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
                                                message={errors.frecuencia}
                                            />
                                        </div>

                                        {frecuencia === 'SEMANAL' ? (
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
                                                            (dia, indice) => (
                                                                <SelectItem
                                                                    key={dia}
                                                                    value={String(
                                                                        indice +
                                                                            1,
                                                                    )}
                                                                >
                                                                    {dia}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.dia_semana}
                                                />
                                            </div>
                                        ) : (
                                            <div className="grid gap-2">
                                                <Label htmlFor="dia_mes">
                                                    Día del mes
                                                </Label>
                                                <Select
                                                    name="dia_mes"
                                                    defaultValue={String(
                                                        recordatorio.dia_mes ??
                                                            1,
                                                    )}
                                                >
                                                    <SelectTrigger
                                                        id="dia_mes"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Array.from(
                                                            { length: 28 },
                                                            (_, indice) =>
                                                                indice + 1,
                                                        ).map((dia) => (
                                                            <SelectItem
                                                                key={dia}
                                                                value={String(
                                                                    dia,
                                                                )}
                                                            >
                                                                {dia}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.dia_mes}
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
                                                onChange={(evento) =>
                                                    setHoraEnvio(
                                                        evento.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {local ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Equivale a las {local} en tu
                                                    hora local.
                                                </p>
                                            ) : null}
                                            <InputError
                                                message={errors.hora_envio}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid max-w-xs gap-2">
                                        <Label htmlFor="dias_anticipacion">
                                            Avisar los que vencen en los
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
                                            message={errors.dias_anticipacion}
                                        />
                                    </div>
                                </Seccion>

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
                                                Enviar a cada cliente sus
                                                equipos
                                            </Label>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Se envía al correo registrado del
                                            cliente, con los equipos de los que
                                            es dueño.
                                        </p>
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
                                                Cada usuario asignado a bahías
                                                recibe los equipos de sus
                                                bahías, agrupados por bahía.
                                            </p>
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
                                                    <Users /> Asignar bahías a
                                                    usuarios
                                                </Button>
                                            </AsignarUsuariosBahiasModal>
                                        ) : null}
                                    </div>

                                    <CorreosCopia
                                        iniciales={recordatorio.correos_copia}
                                        errores={errors}
                                    />
                                </Seccion>

                                {puedeEditar ? (
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            loading={processing}
                                            data-test="recordatorio-guardar"
                                        >
                                            Guardar recordatorio
                                        </Button>
                                    </div>
                                ) : null}
                            </fieldset>
                        )}
                    </Form>

                    <aside className="h-fit space-y-4 rounded-lg border p-4">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Si se enviara hoy
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Con el rango guardado de{' '}
                                {recordatorio.dias_anticipacion} días.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-2">
                            <Contador
                                valor={resumen.vencidos}
                                etiqueta="Equipos vencidos"
                                className="text-red-600 dark:text-red-400"
                            />
                            <Contador
                                valor={resumen.proximos_a_vencer}
                                etiqueta="Próximos a vencer"
                                className="text-amber-600 dark:text-amber-400"
                            />
                            <Contador
                                valor={resumen.agendados}
                                etiqueta="Agendados"
                                className="text-blue-600 dark:text-blue-400"
                            />
                        </div>

                        <dl className="grid gap-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Clientes
                                </dt>
                                <dd className="font-medium">
                                    {resumen.clientes}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Usuarios de bahía
                                </dt>
                                <dd className="font-medium">
                                    {resumen.usuarios_bahia}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Último envío
                                </dt>
                                <dd className="text-right font-medium">
                                    {recordatorio.ultimo_envio_at
                                        ? new Date(
                                              recordatorio.ultimo_envio_at,
                                          ).toLocaleString('es')
                                        : 'Nunca'}
                                </dd>
                            </div>
                        </dl>
                    </aside>
                </div>
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
