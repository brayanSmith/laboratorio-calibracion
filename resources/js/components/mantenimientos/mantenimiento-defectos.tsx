import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ColumnResizeHandle from '@/components/column-resize-handle';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useResizableColumns } from '@/hooks/use-resizable-columns';
import { destroy } from '@/routes/defectos';
import type { MantenimientoDefecto, NivelRiesgo } from '@/types';

const nivelesRiesgo: { value: NivelRiesgo; label: string }[] = [
    { value: 'ALTO', label: 'Alto' },
    { value: 'MEDIO', label: 'Medio' },
    { value: 'BAJO', label: 'Bajo' },
];

/** Alto en rojo, medio en amarillo, bajo en verde. */
const colorNivelRiesgo: Record<NivelRiesgo, string> = {
    ALTO: '!border-transparent !bg-red-500 !text-white',
    MEDIO: '!border-transparent !bg-amber-500 !text-white',
    BAJO: '!border-transparent !bg-emerald-500 !text-white',
};

/** La fila/tarjeta se tiñe según el nivel de riesgo, para que se note de un vistazo
 * sin tener que leer la insignia. */
const colorFilaNivelRiesgo: Record<NivelRiesgo, string> = {
    ALTO: 'bg-red-50 dark:bg-red-950/30',
    MEDIO: 'bg-amber-50 dark:bg-amber-950/30',
    BAJO: 'bg-emerald-50 dark:bg-emerald-950/30',
};

/** El botón activo del toggle también se tiñe según el nivel, igual que la insignia. */
const colorToggleNivelRiesgo: Record<NivelRiesgo, string> = {
    ALTO: 'data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white',
    MEDIO: 'data-[state=on]:!border-amber-500 data-[state=on]:!bg-amber-500 data-[state=on]:!text-white',
    BAJO: 'data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white',
};

type Props = {
    defectos: MantenimientoDefecto[];
    errors: Partial<Record<string, string>>;
};

type FilaBorrador = {
    key: number;
    defectoIdentificado: string;
    nivelRiesgo: NivelRiesgo;
    accionCorrectiva: string;
};

type NivelRiesgoToggleProps = {
    name: string;
    value: NivelRiesgo;
    onChange: (value: NivelRiesgo) => void;
    className?: string;
};

/** Toggle agrupado (en vez de un select) para elegir el nivel de riesgo, con el
 * mismo código de color que la insignia/fila. */
function NivelRiesgoToggle({
    name,
    value,
    onChange,
    className,
}: NivelRiesgoToggleProps) {
    return (
        <>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={value}
                onValueChange={(nuevo) => {
                    if (nuevo) {
                        onChange(nuevo as NivelRiesgo);
                    }
                }}
                className={className}
            >
                {nivelesRiesgo.map((option) => (
                    <ToggleGroupItem
                        key={option.value}
                        value={option.value}
                        className={`grow basis-0 text-xs ${colorToggleNivelRiesgo[option.value]}`}
                    >
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <input type="hidden" name={name} value={value} />
        </>
    );
}

/** Relación 1:* (un mantenimiento puede tener varios defectos). Las filas nuevas
 * quedan disponibles para registrarse al presionar el botón "Guardar" de la modal.
 * Las columnas se pueden redimensionar arrastrando su separador, al estilo Excel, y
 * la tabla tiene su propio scroll horizontal (no afecta al resto de la modal). */
export default function MantenimientoDefectos({ defectos, errors }: Props) {
    const siguienteKey = useRef(0);
    const filaVacia = (): FilaBorrador => ({
        key: siguienteKey.current++,
        defectoIdentificado: '',
        nivelRiesgo: 'BAJO',
        accionCorrectiva: '',
    });

    const [filas, setFilas] = useState<FilaBorrador[]>(() => [filaVacia()]);
    const { anchos, iniciarRedimension } = useResizableColumns({
        defecto: 260,
        nivelRiesgo: 160,
        accionCorrectiva: 220,
    });

    const actualizarFila = (key: number, cambios: Partial<FilaBorrador>) =>
        setFilas((prev) =>
            prev.map((fila) =>
                fila.key === key ? { ...fila, ...cambios } : fila,
            ),
        );

    const quitarFila = (key: number) =>
        setFilas((prev) => prev.filter((otra) => otra.key !== key));

    return (
        <div className="min-w-0 space-y-2">
            <Label>Defectos identificados</Label>

            <div className="max-w-full min-w-0 overflow-x-auto rounded-lg border">
                <table
                    className="text-sm"
                    style={{
                        width:
                            anchos.defecto +
                            anchos.nivelRiesgo +
                            anchos.accionCorrectiva +
                            40,
                        tableLayout: 'fixed',
                    }}
                >
                    <colgroup>
                        <col style={{ width: anchos.defecto }} />
                        <col style={{ width: anchos.nivelRiesgo }} />
                        <col style={{ width: anchos.accionCorrectiva }} />
                        <col style={{ width: 40 }} />
                    </colgroup>
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="relative px-3 py-2 font-medium">
                                Defecto
                                <ColumnResizeHandle
                                    label="Defecto"
                                    onPointerDown={iniciarRedimension(
                                        'defecto',
                                    )}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Nivel de riesgo
                                <ColumnResizeHandle
                                    label="Nivel de riesgo"
                                    onPointerDown={iniciarRedimension(
                                        'nivelRiesgo',
                                    )}
                                />
                            </th>
                            <th className="relative px-3 py-2 font-medium">
                                Acción correctiva
                                <ColumnResizeHandle
                                    label="Acción correctiva"
                                    onPointerDown={iniciarRedimension(
                                        'accionCorrectiva',
                                    )}
                                />
                            </th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody
                        className="divide-y"
                        data-test="mantenimiento-defectos-list"
                    >
                        {defectos.map((defecto) => (
                            <tr
                                key={`saved-${defecto.id}`}
                                className={
                                    colorFilaNivelRiesgo[defecto.nivel_riesgo]
                                }
                                data-test="mantenimiento-defecto-item"
                            >
                                <td className="truncate px-3 py-2 font-medium">
                                    {defecto.defecto_identificado}
                                </td>
                                <td className="px-3 py-2">
                                    <Badge
                                        className={
                                            colorNivelRiesgo[
                                                defecto.nivel_riesgo
                                            ]
                                        }
                                    >
                                        {
                                            nivelesRiesgo.find(
                                                (option) =>
                                                    option.value ===
                                                    defecto.nivel_riesgo,
                                            )?.label
                                        }
                                    </Badge>
                                </td>
                                <td className="truncate px-3 py-2 text-muted-foreground">
                                    {defecto.accion_correctiva || '—'}
                                </td>
                                <td className="px-3 py-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-6 w-6"
                                        aria-label="Quitar defecto"
                                        onClick={() =>
                                            router.delete(
                                                destroy.url(defecto.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        ))}

                        {filas.map((fila, index) => (
                            <tr
                                key={`draft-${fila.key}`}
                                className={
                                    colorFilaNivelRiesgo[fila.nivelRiesgo]
                                }
                                data-test="defecto-fila-nueva"
                            >
                                <td className="px-3 py-2">
                                    <Textarea
                                        name={`nuevos_defectos[${index}][defecto_identificado]`}
                                        value={fila.defectoIdentificado}
                                        onChange={(event) =>
                                            actualizarFila(fila.key, {
                                                defectoIdentificado:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Describe el defecto"
                                        rows={1}
                                        className="field-sizing-content max-h-20 min-h-7 w-full resize-none overflow-y-auto rounded-none border-0 bg-transparent p-1 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `nuevos_defectos.${index}.defecto_identificado`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <NivelRiesgoToggle
                                        name={`nuevos_defectos[${index}][nivel_riesgo]`}
                                        value={fila.nivelRiesgo}
                                        onChange={(value) =>
                                            actualizarFila(fila.key, {
                                                nivelRiesgo: value,
                                            })
                                        }
                                        className="w-full"
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `nuevos_defectos.${index}.nivel_riesgo`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <Textarea
                                        name={`nuevos_defectos[${index}][accion_correctiva]`}
                                        value={fila.accionCorrectiva}
                                        onChange={(event) =>
                                            actualizarFila(fila.key, {
                                                accionCorrectiva:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Opcional"
                                        rows={1}
                                        className="field-sizing-content max-h-20 min-h-7 w-full resize-none overflow-y-auto rounded-none border-0 bg-transparent p-1 text-xs shadow-none focus-visible:ring-1 focus-visible:ring-primary focus-visible:ring-inset"
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `nuevos_defectos.${index}.accion_correctiva`
                                            ]
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-6 w-6"
                                        aria-label="Quitar fila"
                                        disabled={filas.length === 1}
                                        onClick={() => quitarFila(fila.key)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setFilas((prev) => [...prev, filaVacia()])}
                data-test="agregar-fila-defecto-button"
            >
                <Plus className="h-4 w-4" /> Agregar otro defecto
            </Button>
        </div>
    );
}
