import { Form, router } from '@inertiajs/react';
import {
    CircleCheck,
    GripVertical,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import RecordatorioPlantillaController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioPlantillaController';
import InputError from '@/components/input-error';
import {
    EliminarPlantillaDialog,
    NuevaPlantillaDialog,
} from '@/components/recordatorios/dialogos-plantilla';
import type { EditorCorreoHandle } from '@/components/recordatorios/editor-correo';
import EditorCorreo from '@/components/recordatorios/editor-correo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    PlantillaPersonalizada,
    PlantillaRecordatorio,
    PlantillasCorreo,
    TipoPlantillaRecordatorio,
} from '@/types';

/** {tabla} y {detalle} solo tienen sentido en el mensaje, no en el asunto. */
const SOLO_MENSAJE = ['{tabla}', '{detalle}'];

/** Valor del selector para la plantilla predeterminada. */
const PREDETERMINADA = 'predeterminada';

const TIPOS: Record<
    TipoPlantillaRecordatorio,
    { titulo: string; descripcion: string }
> = {
    cliente: {
        titulo: 'Correo a clientes',
        descripcion: 'Lo recibe cada cliente con sus equipos.',
    },
    bahia: {
        titulo: 'Resumen por bahía',
        descripcion:
            'Lo reciben los usuarios asignados a bahías y los correos con copia.',
    },
};

const TIPOS_ORDENADOS = Object.keys(TIPOS) as TipoPlantillaRecordatorio[];

/** Identifica una plantilla de un correo: "cliente:predeterminada", "bahia:12". */
function claveDe(tipo: TipoPlantillaRecordatorio, seleccion: string): string {
    return `${tipo}:${seleccion}`;
}

/** La plantilla elegida si todavía existe; si no (ej. se eliminó), la predeterminada. */
function seleccionValida(correo: PlantillasCorreo, seleccion: string): string {
    return seleccion === PREDETERMINADA ||
        correo.personalizadas.some(
            (plantilla) => String(plantilla.id) === seleccion,
        )
        ? seleccion
        : PREDETERMINADA;
}

/**
 * Escribe el texto en la posición del cursor del input, como si lo hubiera tecleado el
 * usuario: el evento input hace que React actualice su estado.
 */
function insertarEnInput(campo: HTMLInputElement, texto: string) {
    const inicio = campo.selectionStart ?? campo.value.length;
    const fin = campo.selectionEnd ?? campo.value.length;

    campo.focus();
    campo.setRangeText(texto, inicio, fin, 'end');
    campo.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Fichas de variables: se arrastran al asunto o al mensaje (caen donde se sueltan) o,
 * con un clic, se insertan donde está el cursor del último campo editado.
 */
function VariablesPlantilla({
    variables,
    editable,
    onArrastrar,
    onInsertar,
}: {
    variables: Record<string, string>;
    editable: boolean;
    onArrastrar: (variable: string | null) => void;
    onInsertar: (variable: string) => void;
}) {
    return (
        <aside className="grid h-fit gap-3 rounded-lg border bg-muted/30 p-4 lg:sticky lg:top-4">
            <div>
                <h3 className="text-sm font-semibold">Variables</h3>
                <p className="text-xs text-muted-foreground">
                    Arrástralas al asunto o al mensaje, o haz clic para
                    insertarlas donde está el cursor. Se reemplazan al enviar
                    cada correo.
                </p>
            </div>

            <ul className="grid gap-2">
                {Object.entries(variables).map(([variable, significado]) => (
                    <li key={variable} className="grid gap-0.5">
                        <button
                            type="button"
                            draggable={editable}
                            disabled={!editable}
                            title={`${significado}. Arrastra o haz clic para insertar.`}
                            onDragStart={(evento) => {
                                evento.dataTransfer.setData(
                                    'text/plain',
                                    variable,
                                );
                                evento.dataTransfer.effectAllowed = 'copy';
                                onArrastrar(variable);
                            }}
                            onDragEnd={() => onArrastrar(null)}
                            onClick={() => onInsertar(variable)}
                            className="flex w-fit cursor-grab items-center gap-1 rounded-md bg-sidebar-primary py-1 pr-2.5 pl-1.5 font-mono text-xs font-medium text-sidebar-primary-foreground shadow-sm transition hover:-translate-y-px hover:shadow-md active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                            data-test={`recordatorio-variable-${variable.slice(1, -1)}`}
                        >
                            <GripVertical className="size-3 opacity-70" />
                            {variable}
                        </button>
                        <span className="pl-1 text-xs text-muted-foreground">
                            {significado}
                        </span>
                    </li>
                ))}
            </ul>
        </aside>
    );
}

/**
 * Formulario de una plantilla: nombre (si no es la predeterminada), asunto y mensaje, con
 * su propio botón de guardar. La predeterminada además se puede restaurar a los textos
 * por defecto.
 */
function EditorPlantilla({
    tipo,
    plantilla,
    porDefecto,
    editable,
    arrastrando,
    asuntoRef,
    mensajeRef,
    onEnfocar,
}: {
    tipo: TipoPlantillaRecordatorio;
    /** null: la predeterminada. */
    plantilla: PlantillaPersonalizada | null;
    /** Los textos con que empieza el formulario. */
    porDefecto: PlantillaRecordatorio & { inicial: PlantillaRecordatorio };
    editable: boolean;
    arrastrando: string | null;
    asuntoRef: (campo: HTMLInputElement | null) => void;
    mensajeRef: (editor: EditorCorreoHandle | null) => void;
    onEnfocar: (campo: 'asunto' | 'mensaje') => void;
}) {
    const editor = useRef<EditorCorreoHandle | null>(null);
    const inicial = porDefecto.inicial;
    const [asunto, setAsunto] = useState(inicial.asunto);
    const [mensajeCambiado, setMensajeCambiado] = useState(
        inicial.mensaje !== porDefecto.mensaje,
    );
    const esPredeterminada = plantilla === null;
    const esPorDefecto = asunto === porDefecto.asunto && !mensajeCambiado;
    const id = plantilla ? String(plantilla.id) : PREDETERMINADA;
    const accion = plantilla
        ? RecordatorioPlantillaController.update.form(plantilla.id)
        : RecordatorioPlantillaController.actualizarPredeterminada.form(tipo);

    return (
        <Form
            {...accion}
            options={{ preserveScroll: true }}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <fieldset disabled={!editable} className="grid gap-4">
                    {plantilla ? (
                        <div className="grid gap-2">
                            <Label htmlFor={`nombre_${tipo}_${id}`}>
                                Nombre de la plantilla
                            </Label>
                            <Input
                                id={`nombre_${tipo}_${id}`}
                                name="nombre"
                                maxLength={80}
                                defaultValue={plantilla.nombre}
                                required
                            />
                            <InputError message={errors.nombre} />
                        </div>
                    ) : null}

                    <div className="grid gap-2">
                        <Label htmlFor={`asunto_${tipo}_${id}`}>Asunto</Label>
                        <Input
                            ref={asuntoRef}
                            id={`asunto_${tipo}_${id}`}
                            name="asunto"
                            maxLength={150}
                            value={asunto}
                            required={!esPredeterminada}
                            onChange={(evento) =>
                                setAsunto(evento.target.value)
                            }
                            onFocus={() => onEnfocar('asunto')}
                            onDrop={(evento) => {
                                if (
                                    SOLO_MENSAJE.includes(
                                        evento.dataTransfer.getData(
                                            'text/plain',
                                        ),
                                    )
                                ) {
                                    evento.preventDefault();
                                }
                            }}
                            className={cn(
                                arrastrando &&
                                    !SOLO_MENSAJE.includes(arrastrando) &&
                                    'border-dashed border-primary ring-2 ring-primary/20',
                            )}
                        />
                        <InputError message={errors.asunto} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`mensaje_${tipo}_${id}`}>Mensaje</Label>
                        <EditorCorreo
                            ref={(handle) => {
                                editor.current = handle;
                                mensajeRef(handle);
                            }}
                            id={`mensaje_${tipo}_${id}`}
                            name="mensaje"
                            inicial={inicial.mensaje}
                            editable={editable}
                            resaltado={arrastrando !== null}
                            onCambiar={() => setMensajeCambiado(true)}
                            onEnfocar={() => onEnfocar('mensaje')}
                        />
                        <InputError message={errors.mensaje} />
                    </div>

                    {editable ? (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            {esPredeterminada ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={esPorDefecto}
                                    onClick={() => {
                                        setAsunto(porDefecto.asunto);
                                        editor.current?.establecer(
                                            porDefecto.mensaje,
                                        );
                                        setMensajeCambiado(false);
                                    }}
                                    data-test={`recordatorio-restaurar-${tipo}`}
                                >
                                    <RotateCcw /> Restaurar texto por defecto
                                </Button>
                            ) : null}
                            <Button
                                type="submit"
                                loading={processing}
                                data-test={`recordatorio-plantilla-guardar-${tipo}`}
                            >
                                Guardar plantilla
                            </Button>
                        </div>
                    ) : null}
                </fieldset>
            )}
        </Form>
    );
}

/**
 * Pestaña de contenido del correo: para el correo a clientes y el resumen por bahía, la
 * plantilla predeterminada y las creadas (ej. "Navidad"). Se elige cuál editar, cuál está
 * en uso (con la que sale el correo) y, al lado, las variables para arrastrar.
 */
export default function ContenidoCorreo({
    plantillas,
    porDefecto,
    variables,
    editable,
}: {
    plantillas: Record<TipoPlantillaRecordatorio, PlantillasCorreo>;
    porDefecto: Record<TipoPlantillaRecordatorio, PlantillaRecordatorio>;
    variables: Record<string, string>;
    editable: boolean;
}) {
    const [tipo, setTipo] = useState<TipoPlantillaRecordatorio>('cliente');
    const [seleccion, setSeleccion] = useState<
        Record<TipoPlantillaRecordatorio, string>
    >(() => ({
        cliente: String(plantillas.cliente.en_uso ?? PREDETERMINADA),
        bahia: String(plantillas.bahia.en_uso ?? PREDETERMINADA),
    }));
    const [creando, setCreando] = useState(false);
    const [eliminando, setEliminando] = useState(false);
    /** Nombre de la plantilla recién creada, para seleccionarla cuando llegue. */
    const [porSeleccionar, setPorSeleccionar] = useState<{
        tipo: TipoPlantillaRecordatorio;
        nombre: string;
    } | null>(null);
    const [poniendoEnUso, setPoniendoEnUso] = useState(false);
    const [variableArrastrada, setVariableArrastrada] = useState<string | null>(
        null,
    );
    const asuntos = useRef<Record<string, HTMLInputElement | null>>({});
    const mensajes = useRef<Record<string, EditorCorreoHandle | null>>({});
    const ultimoCampo = useRef<{
        clave: string;
        campo: 'asunto' | 'mensaje';
    } | null>(null);

    const correo = plantillas[tipo];
    const elegida = seleccionValida(correo, seleccion[tipo]);
    const enUso = String(correo.en_uso ?? PREDETERMINADA);
    const personalizadaElegida =
        correo.personalizadas.find(
            (plantilla) => String(plantilla.id) === elegida,
        ) ?? null;

    useEffect(() => {
        if (!porSeleccionar) {
            return;
        }

        const creada = plantillas[porSeleccionar.tipo].personalizadas.find(
            (plantilla) => plantilla.nombre === porSeleccionar.nombre,
        );

        if (creada) {
            setSeleccion((actual) => ({
                ...actual,
                [porSeleccionar.tipo]: String(creada.id),
            }));
            setPorSeleccionar(null);
        }
    }, [plantillas, porSeleccionar]);

    /** Inserta la variable en el último campo editado de la plantilla visible (o en su mensaje). */
    function insertarVariable(variable: string) {
        const clave = claveDe(tipo, elegida);
        const enAsunto =
            ultimoCampo.current?.clave === clave &&
            ultimoCampo.current.campo === 'asunto' &&
            !SOLO_MENSAJE.includes(variable);
        const asunto = asuntos.current[clave];

        if (enAsunto && asunto) {
            insertarEnInput(asunto, variable);

            return;
        }

        mensajes.current[clave]?.insertar(variable);
    }

    function ponerEnUso() {
        router.put(
            RecordatorioPlantillaController.usar.url(tipo),
            {
                plantilla_id:
                    elegida === PREDETERMINADA ? null : Number(elegida),
            },
            {
                preserveScroll: true,
                onStart: () => setPoniendoEnUso(true),
                onFinish: () => setPoniendoEnUso(false),
            },
        );
    }

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
            <div className="grid h-fit gap-4">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={tipo}
                    onValueChange={(valor) => {
                        if (valor) {
                            setTipo(valor as TipoPlantillaRecordatorio);
                        }
                    }}
                    className="w-full sm:w-fit"
                >
                    {TIPOS_ORDENADOS.map((tipoPlantilla) => (
                        <ToggleGroupItem
                            key={tipoPlantilla}
                            value={tipoPlantilla}
                            className="px-4"
                            data-test={`recordatorio-plantilla-${tipoPlantilla}`}
                        >
                            {TIPOS[tipoPlantilla].titulo}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>

                <p className="text-sm text-muted-foreground">
                    {TIPOS[tipo].descripcion}
                </p>

                <div className="flex flex-wrap items-end gap-2 rounded-lg border bg-muted/30 p-3">
                    <div className="grid min-w-56 flex-1 gap-1.5 sm:flex-none">
                        <Label htmlFor={`plantilla_${tipo}`}>Plantilla</Label>
                        <Select
                            value={elegida}
                            onValueChange={(valor) =>
                                setSeleccion((actual) => ({
                                    ...actual,
                                    [tipo]: valor,
                                }))
                            }
                        >
                            <SelectTrigger
                                id={`plantilla_${tipo}`}
                                className="w-full sm:w-64"
                                data-test={`recordatorio-plantilla-selector-${tipo}`}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={PREDETERMINADA}>
                                    Predeterminada
                                    {enUso === PREDETERMINADA
                                        ? ' · en uso'
                                        : ''}
                                </SelectItem>
                                {correo.personalizadas.map((plantilla) => (
                                    <SelectItem
                                        key={plantilla.id}
                                        value={String(plantilla.id)}
                                    >
                                        {plantilla.nombre}
                                        {enUso === String(plantilla.id)
                                            ? ' · en uso'
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {elegida === enUso ? (
                        <Badge
                            variant="secondary"
                            className="h-9 gap-1.5 px-3"
                            data-test={`recordatorio-plantilla-en-uso-${tipo}`}
                        >
                            <CircleCheck className="size-4 text-emerald-600" />
                            En uso: el correo sale con esta plantilla
                        </Badge>
                    ) : editable ? (
                        <Button
                            type="button"
                            loading={poniendoEnUso}
                            onClick={ponerEnUso}
                            data-test={`recordatorio-plantilla-usar-${tipo}`}
                        >
                            <CircleCheck /> Usar esta plantilla
                        </Button>
                    ) : (
                        <Badge variant="outline" className="h-9 px-3">
                            No está en uso
                        </Badge>
                    )}

                    {editable ? (
                        <div className="flex gap-2 sm:ml-auto">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreando(true)}
                                data-test={`recordatorio-plantilla-nueva-${tipo}`}
                            >
                                <Plus /> Nueva plantilla
                            </Button>
                            {personalizadaElegida ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setEliminando(true)}
                                    aria-label="Eliminar plantilla"
                                    title="Eliminar plantilla"
                                    data-test={`recordatorio-plantilla-eliminar-${tipo}`}
                                >
                                    <Trash2 />
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {/*
                 * Todas quedan montadas (solo se ve la elegida) para no perder lo que se
                 * estaba escribiendo al cambiar de plantilla o de correo.
                 */}
                {TIPOS_ORDENADOS.flatMap((tipoPlantilla) => {
                    const datos = plantillas[tipoPlantilla];
                    const visible = seleccionValida(
                        datos,
                        seleccion[tipoPlantilla],
                    );
                    const opciones: (PlantillaPersonalizada | null)[] = [
                        null,
                        ...datos.personalizadas,
                    ];

                    return opciones.map((plantilla) => {
                        const id = plantilla
                            ? String(plantilla.id)
                            : PREDETERMINADA;
                        const clave = claveDe(tipoPlantilla, id);

                        return (
                            <div
                                key={clave}
                                className={cn(
                                    (tipoPlantilla !== tipo ||
                                        id !== visible) &&
                                        'hidden',
                                )}
                            >
                                <EditorPlantilla
                                    tipo={tipoPlantilla}
                                    plantilla={plantilla}
                                    porDefecto={{
                                        ...porDefecto[tipoPlantilla],
                                        inicial:
                                            plantilla ?? datos.predeterminada,
                                    }}
                                    editable={editable}
                                    arrastrando={variableArrastrada}
                                    asuntoRef={(campo) => {
                                        asuntos.current[clave] = campo;
                                    }}
                                    mensajeRef={(editor) => {
                                        mensajes.current[clave] = editor;
                                    }}
                                    onEnfocar={(campo) => {
                                        ultimoCampo.current = { clave, campo };
                                    }}
                                />
                            </div>
                        );
                    });
                })}

                {editable ? (
                    <p className="text-xs text-muted-foreground">
                        Guarda la plantilla y, en Configuración → Destinatarios,
                        usa «Enviarme una prueba» para ver cómo queda el correo
                        con la plantilla en uso.
                    </p>
                ) : null}
            </div>

            <VariablesPlantilla
                variables={variables}
                editable={editable}
                onArrastrar={setVariableArrastrada}
                onInsertar={insertarVariable}
            />

            {editable ? (
                <NuevaPlantillaDialog
                    tipo={tipo}
                    tituloCorreo={TIPOS[tipo].titulo}
                    open={creando}
                    onOpenChange={setCreando}
                    onCreada={(nombre) => setPorSeleccionar({ tipo, nombre })}
                />
            ) : null}

            {editable && personalizadaElegida ? (
                <EliminarPlantillaDialog
                    plantilla={personalizadaElegida}
                    enUso={elegida === enUso}
                    open={eliminando}
                    onOpenChange={setEliminando}
                    onEliminada={() =>
                        setSeleccion((actual) => ({
                            ...actual,
                            [tipo]: PREDETERMINADA,
                        }))
                    }
                />
            ) : null}
        </div>
    );
}
