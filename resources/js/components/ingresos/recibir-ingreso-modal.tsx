import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { actualizarEstado } from '@/actions/App/Http/Controllers/IngresoController';
import Combobox from '@/components/combobox';
import FirmaCanvas from '@/components/firma-canvas';
import {
    AgregarEquipoCorrectivo,
    type CorrectivoPendiente,
    CorrectivoPendienteItem,
} from '@/components/ingresos/agregar-equipo-correctivo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { usePermissions } from '@/hooks/use-permissions';
import { estadosIngreso } from '@/lib/estados-ingreso';
import type {
    EquipoProgramacionBusquedaItem,
    EstadoIngreso,
    Ingreso,
    IngresoOption,
} from '@/types';

type Props = {
    ingreso: Ingreso | null;
    tecnicos: IngresoOption[];
    clientes: IngresoOption[];
    novedadesIngreso: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function toComboboxOptions(options: IngresoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/** Pendiente en amarillo, recibido en verde, cancelado en rojo. */
const colorToggleEstadoIngreso: Record<EstadoIngreso, string> = {
    PENDIENTE: 'data-[state=on]:!bg-amber-500 data-[state=on]:!text-white',
    RECIBIDO: 'data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white',
    CANCELADO: 'data-[state=on]:!bg-red-500 data-[state=on]:!text-white',
};

/**
 * Recibir, cancelar o devolver a pendiente un ingreso. El estado se elige con un
 * toggle group de colores; según la opción, aparecen los campos que le corresponden: si
 * queda Recibido, primero los equipos programados/no programados y, al pulsar
 * "Siguiente", técnico/cliente/firma/novedad; si queda Cancelado, el motivo; si vuelve a
 * Pendiente, nada más.
 */
export default function RecibirIngresoModal({
    ingreso,
    tecnicos,
    clientes,
    novedadesIngreso,
    open,
    onOpenChange,
}: Props) {
    if (!ingreso) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <RecibirIngresoFormulario
                    key={`${ingreso.id}-${String(open)}`}
                    ingreso={ingreso}
                    tecnicos={tecnicos}
                    clientes={clientes}
                    novedadesIngreso={novedadesIngreso}
                    onSuccess={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

type FormularioProps = {
    ingreso: Ingreso;
    tecnicos: IngresoOption[];
    clientes: IngresoOption[];
    novedadesIngreso: IngresoOption[];
    onSuccess: () => void;
};

/**
 * Montado de nuevo (por la key en RecibirIngresoModal) cada vez que cambia el ingreso
 * o se vuelve a abrir el modal, para que su estado local arranque siempre desde los
 * datos actuales de ese ingreso.
 */
function RecibirIngresoFormulario({
    ingreso,
    tecnicos,
    clientes,
    novedadesIngreso,
    onSuccess,
}: FormularioProps) {
    const { can } = usePermissions();
    const [estadoIngreso, setEstadoIngreso] = useState<EstadoIngreso>(
        ingreso.estado_ingreso,
    );
    const [tecnicoId, setTecnicoId] = useState(
        ingreso.tecnico_recibe_id?.toString(),
    );
    const [clienteId, setClienteId] = useState(
        ingreso.cliente_entrega_id?.toString(),
    );
    const [mostrarAgregar, setMostrarAgregar] = useState(false);
    const [correctivosPendientes, setCorrectivosPendientes] = useState<
        CorrectivoPendiente[]
    >([]);
    // Si ya tenía técnico y cliente (una recepción anterior), se muestran de una vez al
    // editar; si no, se dejan ocultos detrás de "Siguiente" para no saturar al usuario
    // con todos los campos a la vez mientras revisa los equipos.
    const [mostrarDatosRecepcion, setMostrarDatosRecepcion] = useState(
        Boolean(ingreso.tecnico_recibe_id && ingreso.cliente_entrega_id),
    );
    // Se activa si se intenta guardar sin técnico/cliente, para mostrar el error ahí
    // mismo en el navegador, sin gastar una petición al servidor solo para eso.
    const [intentoGuardar, setIntentoGuardar] = useState(false);
    const aprobado = estadoIngreso === 'RECIBIDO';
    const cancelado = estadoIngreso === 'CANCELADO';
    const equiposProgramados = ingreso.equipo_programaciones.filter(
        (item) => item.tipo_mantenimiento === 'PREVENTIVO' && item.agendar,
    );
    const equiposNoProgramados = ingreso.equipo_programaciones.filter(
        (item) => item.tipo_mantenimiento === 'CORRECTIVO',
    );

    return (
        <Form
            {...actualizarEstado.form(ingreso.id)}
            className="space-y-6"
            onBefore={() => {
                if (aprobado && (!tecnicoId || !clienteId)) {
                    setIntentoGuardar(true);

                    return false;
                }

                return true;
            }}
            onSuccess={onSuccess}
        >
            {({ errors, processing }) => (
                <>
                    <DialogHeader>
                        <DialogTitle>Recibir equipos</DialogTitle>
                        <DialogDescription>
                            Define el estado del ingreso y completa lo que
                            corresponda
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label>Estado del ingreso</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={estadoIngreso}
                            onValueChange={(valor) => {
                                if (valor) {
                                    setEstadoIngreso(valor as EstadoIngreso);
                                }
                            }}
                            className="w-full"
                        >
                            {estadosIngreso.map((opcion) => (
                                <ToggleGroupItem
                                    key={opcion.value}
                                    value={opcion.value}
                                    className={`flex-1 ${colorToggleEstadoIngreso[opcion.value as EstadoIngreso]}`}
                                >
                                    {opcion.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <input
                            type="hidden"
                            name="estado_ingreso"
                            value={estadoIngreso}
                        />
                        <InputError message={errors.estado_ingreso} />
                    </div>

                    {aprobado && equiposProgramados.length > 0 ? (
                        <div className="space-y-1.5">
                            <Label>Equipos programados</Label>
                            <ul
                                className="max-h-48 divide-y overflow-y-auto rounded-lg border bg-background"
                                data-test="equipos-recibidos-list"
                            >
                                {equiposProgramados.map((item) => (
                                    <EquipoRecibido
                                        key={item.id}
                                        item={item}
                                        novedadesIngreso={novedadesIngreso}
                                        errors={errors}
                                    />
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    {aprobado &&
                    (equiposNoProgramados.length > 0 ||
                        correctivosPendientes.length > 0) ? (
                        <div className="space-y-1.5">
                            <Label>Equipos no programados</Label>
                            <ul
                                className="max-h-48 divide-y overflow-y-auto rounded-lg border bg-background"
                                data-test="correctivos-recibidos-list"
                            >
                                {equiposNoProgramados.map((item) => (
                                    <EquipoRecibido
                                        key={item.id}
                                        item={item}
                                        novedadesIngreso={novedadesIngreso}
                                        errors={errors}
                                    />
                                ))}
                                {correctivosPendientes.map(
                                    (pendiente, index) => (
                                        <CorrectivoPendienteItem
                                            key={pendiente.tempId}
                                            pendiente={pendiente}
                                            index={index}
                                            campo="equipos_correctivos_recibidos"
                                            errors={errors}
                                            onQuitar={() =>
                                                setCorrectivosPendientes(
                                                    (actuales) =>
                                                        actuales.filter(
                                                            (item) =>
                                                                item.tempId !==
                                                                pendiente.tempId,
                                                        ),
                                                )
                                            }
                                        />
                                    ),
                                )}
                            </ul>
                        </div>
                    ) : null}

                    {aprobado && can('equipos.editar') ? (
                        mostrarAgregar ? (
                            <AgregarEquipoCorrectivo
                                bahiaId={ingreso.bahia_id.toString()}
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
                                <Plus className="h-4 w-4" /> Agregar otros
                                equipos
                            </Button>
                        )
                    ) : null}

                    {aprobado && mostrarDatosRecepcion ? (
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tecnico_recibe_id">
                                    Técnico que recibe{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Combobox
                                    id="tecnico_recibe_id"
                                    name="tecnico_recibe_id"
                                    value={tecnicoId}
                                    onValueChange={setTecnicoId}
                                    options={toComboboxOptions(tecnicos)}
                                    placeholder="Selecciona un técnico"
                                    searchPlaceholder="Buscar técnico..."
                                />
                                <InputError
                                    message={
                                        errors.tecnico_recibe_id ??
                                        (intentoGuardar && !tecnicoId
                                            ? 'Selecciona un técnico que recibe.'
                                            : undefined)
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cliente_entrega_id">
                                    Cliente que entrega{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Combobox
                                    id="cliente_entrega_id"
                                    name="cliente_entrega_id"
                                    value={clienteId}
                                    onValueChange={setClienteId}
                                    options={toComboboxOptions(clientes)}
                                    placeholder="Selecciona un cliente"
                                    searchPlaceholder="Buscar cliente..."
                                />
                                <InputError
                                    message={
                                        errors.cliente_entrega_id ??
                                        (intentoGuardar && !clienteId
                                            ? 'Selecciona un cliente que entrega.'
                                            : undefined)
                                    }
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label>Firma del cliente</Label>

                                {ingreso.firma_url ? (
                                    <img
                                        src={ingreso.firma_url}
                                        alt="Firma actual del cliente"
                                        className="h-16 w-fit rounded-md border bg-white object-contain p-1"
                                        data-test="firma-preview"
                                    />
                                ) : null}

                                <Tabs defaultValue="dibujar">
                                    <TabsList>
                                        <TabsTrigger value="dibujar">
                                            Dibujar firma
                                        </TabsTrigger>
                                        <TabsTrigger value="subir">
                                            Subir imagen
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="dibujar">
                                        <FirmaCanvas name="firma_cliente_entrega" />
                                    </TabsContent>

                                    <TabsContent
                                        value="subir"
                                        className="space-y-2"
                                    >
                                        <Input
                                            id="firma_cliente_entrega"
                                            name="firma_cliente_entrega"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Imagen PNG, JPG o WebP de máximo 2
                                            MB.
                                            {ingreso.firma_url
                                                ? ' Si eliges una nueva, reemplaza a la actual.'
                                                : ''}
                                        </p>
                                    </TabsContent>
                                </Tabs>
                                <InputError
                                    message={errors.firma_cliente_entrega}
                                />

                                {ingreso.firma_url ? (
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="eliminar_firma"
                                            name="eliminar_firma"
                                        />
                                        <Label htmlFor="eliminar_firma">
                                            Quitar firma actual
                                        </Label>
                                    </div>
                                ) : null}
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="novedad">
                                    Novedad (opcional)
                                </Label>
                                <Textarea
                                    id="novedad"
                                    name="novedad"
                                    defaultValue={ingreso.novedad ?? ''}
                                    placeholder="Describe cualquier novedad del ingreso"
                                />
                                <InputError message={errors.novedad} />
                            </div>
                        </div>
                    ) : null}

                    {cancelado ? (
                        <div className="grid gap-2">
                            <Label htmlFor="motivo_cancelacion">
                                Motivo de cancelación
                            </Label>
                            <Textarea
                                id="motivo_cancelacion"
                                name="motivo_cancelacion"
                                defaultValue={ingreso.motivo_cancelacion ?? ''}
                                placeholder="Explica por qué se cancela el ingreso"
                            />
                            <InputError message={errors.motivo_cancelacion} />
                        </div>
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancelar</Button>
                        </DialogClose>

                        {aprobado && !mostrarDatosRecepcion ? (
                            <Button
                                type="button"
                                onClick={() => setMostrarDatosRecepcion(true)}
                                data-test="ingreso-recibir-siguiente"
                            >
                                Siguiente
                            </Button>
                        ) : (
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="ingreso-recibir-submit"
                            >
                                Guardar
                            </Button>
                        )}
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

type EquipoRecibidoProps = {
    item: EquipoProgramacionBusquedaItem;
    novedadesIngreso: IngresoOption[];
    errors: Partial<Record<string, string>>;
};

/**
 * Una fila de equipo programado ya enlazado al ingreso, con su toggle de "ingresado".
 * Por defecto llega en true (se espera que todo lo agendado se reciba), pero si se
 * desmarca se trata como un cancelado: pide la novedad y permite registrar el
 * re-agendamiento, igual que antes se hacía desde el formulario del ingreso.
 */
function EquipoRecibido({
    item,
    novedadesIngreso,
    errors,
}: EquipoRecibidoProps) {
    const [ingresado, setIngresado] = useState(item.ingresado);
    const [novedadIngresoId, setNovedadIngresoId] = useState(
        item.novedad_ingreso_id?.toString() ?? '',
    );
    const [observacion, setObservacion] = useState(
        item.observacion_no_ingreso ?? '',
    );
    const [reAgendar, setReAgendar] = useState(item.re_agendar);
    const [fechaAgendamiento, setFechaAgendamiento] = useState(
        item.datos_re_agendamiento?.fecha_proximo_agendamiento ?? '',
    );

    const prefijo = `equipos_recibidos.${item.id}`;

    return (
        <li
            className="space-y-1.5 px-3 py-1.5"
            data-test="equipo-recibido-item"
        >
            <div className="flex items-center justify-between gap-3">
                <div>
                    <span className="text-sm font-medium">
                        {item.equipo.codigo} · {item.equipo.modelo}
                    </span>
                    {item.equipo.cliente ? (
                        <p className="text-xs text-muted-foreground">
                            {item.equipo.cliente.nombre}
                        </p>
                    ) : null}
                </div>

                <Switch
                    checked={ingresado}
                    onCheckedChange={setIngresado}
                    aria-label="Ingresado"
                    className={ingresado ? '!bg-emerald-500' : '!bg-red-500'}
                    data-test="ingresado-toggle"
                />
            </div>

            {!ingresado ? (
                <div className="space-y-2 rounded-md bg-muted/40 p-2">
                    <div className="grid gap-2">
                        <Label
                            htmlFor={`novedad-ingreso-${item.id}`}
                            className="text-xs"
                        >
                            Motivo de no ingreso
                        </Label>
                        <Combobox
                            id={`novedad-ingreso-${item.id}`}
                            value={novedadIngresoId}
                            onValueChange={setNovedadIngresoId}
                            options={novedadesIngreso.map((option) => ({
                                id: option.id,
                                label: option.nombre,
                            }))}
                            placeholder="Selecciona un motivo"
                            searchPlaceholder="Buscar motivo..."
                        />
                        <InputError
                            message={errors[`${prefijo}.novedad_ingreso_id`]}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label
                            htmlFor={`observacion-no-ingreso-${item.id}`}
                            className="text-xs"
                        >
                            Observación (opcional)
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

            <input
                type="hidden"
                name={`equipos_recibidos[${item.id}][ingresado]`}
                value={ingresado ? '1' : '0'}
            />
            {!ingresado ? (
                <>
                    <input
                        type="hidden"
                        name={`equipos_recibidos[${item.id}][novedad_ingreso_id]`}
                        value={novedadIngresoId}
                    />
                    <input
                        type="hidden"
                        name={`equipos_recibidos[${item.id}][observacion_no_ingreso]`}
                        value={observacion}
                    />
                    <input
                        type="hidden"
                        name={`equipos_recibidos[${item.id}][re_agendar]`}
                        value={reAgendar ? '1' : '0'}
                    />
                    {reAgendar ? (
                        <input
                            type="hidden"
                            name={`equipos_recibidos[${item.id}][datos_re_agendamiento][fecha_proximo_agendamiento]`}
                            value={fechaAgendamiento}
                        />
                    ) : null}
                </>
            ) : null}
        </li>
    );
}
