import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { criticidadesEquipo } from '@/lib/criticidad-equipo';
import { cn } from '@/lib/utils';
import type { CriticidadEquipo, Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo?: Equipo;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
    variant?: 'create' | 'edit';
    /** Avisa al padre cuando cambia el tipo de equipo (id como texto). */
    onTipoEquipoChange?: (tipoEquipoId: string | undefined) => void;
    /** Avisa al padre cuando cambia el modelo. */
    onModeloChange?: (modelo: string) => void;
};

const OPCIONES_TECNOLOGIA = [
    { id: 'ANALOGICO', label: 'Analógico' },
    { id: 'DIGITAL', label: 'Digital' },
];

/** Color de cada opción de criticidad al seleccionarla: alta en rojo, media en amarillo, baja en verde. */
const COLOR_TOGGLE_CRITICIDAD: Record<CriticidadEquipo, string> = {
    ALTA: 'data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white',
    MEDIA: 'data-[state=on]:!border-amber-500 data-[state=on]:!bg-amber-500 data-[state=on]:!text-white',
    BAJA: 'data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white',
};

/**
 * Datos generales del equipo. The caller is responsible for its own
 * heading; move this component around freely to reposition the section.
 */
export default function EquipoFormFields({
    equipo,
    options,
    errors,
    variant = 'edit',
    onTipoEquipoChange,
    onModeloChange,
}: Props) {
    const isCreate = variant === 'create';

    const [tipoEquipoId, setTipoEquipoId] = useState(
        equipo?.tipo_equipo_id?.toString(),
    );
    const [tipoTecnologia, setTipoTecnologia] = useState(
        equipo?.tipo_tecnologia,
    );
    const [criticidad, setCriticidad] = useState(
        equipo?.criticidad ?? undefined,
    );
    const [fabricanteId, setFabricanteId] = useState(
        equipo?.fabricante_id?.toString(),
    );
    const [clienteId, setClienteId] = useState(equipo?.cliente_id?.toString());

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="codigo">Código SAP</Label>
                <Input
                    id="codigo"
                    name="codigo"
                    defaultValue={equipo?.codigo}
                    placeholder="EQ-0001"
                    required
                />
                <InputError message={errors.codigo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_equipo_id">Tipo de equipo</Label>
                <Combobox
                    id="tipo_equipo_id"
                    name="tipo_equipo_id"
                    value={tipoEquipoId}
                    onValueChange={(value) => {
                        setTipoEquipoId(value);
                        onTipoEquipoChange?.(value);
                    }}
                    options={options.tipoEquipos.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder="Selecciona un tipo de equipo"
                    searchPlaceholder="Buscar tipo de equipo..."
                />
                <InputError message={errors.tipo_equipo_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_tecnologia">Tipo de indicación</Label>
                <Combobox
                    id="tipo_tecnologia"
                    name="tipo_tecnologia"
                    value={tipoTecnologia}
                    onValueChange={(value) =>
                        setTipoTecnologia(value as Equipo['tipo_tecnologia'])
                    }
                    options={OPCIONES_TECNOLOGIA}
                    placeholder="Selecciona una tecnología"
                />
                <InputError message={errors.tipo_tecnologia} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="modelo">Modelo</Label>
                <Input
                    id="modelo"
                    name="modelo"
                    defaultValue={equipo?.modelo}
                    onChange={(event) => onModeloChange?.(event.target.value)}
                    required
                />
                <InputError message={errors.modelo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="fabricante_id">Fabricante</Label>
                <Combobox
                    id="fabricante_id"
                    name="fabricante_id"
                    value={fabricanteId}
                    onValueChange={setFabricanteId}
                    options={options.fabricantes.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder="Selecciona un fabricante"
                    searchPlaceholder="Buscar fabricante..."
                />
                <InputError message={errors.fabricante_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="numero_serie">Número de serie</Label>
                <Input
                    id="numero_serie"
                    name="numero_serie"
                    defaultValue={equipo?.numero_serie}
                    required
                />
                <InputError message={errors.numero_serie} />
            </div>

            {isCreate ? (
                <input
                    type="hidden"
                    name="condicion_actual"
                    value="Sin evaluar"
                />
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor="condicion_actual">Condición actual</Label>
                    <Input
                        id="condicion_actual"
                        name="condicion_actual"
                        defaultValue={equipo?.condicion_actual}
                        required
                    />
                    <InputError message={errors.condicion_actual} />
                </div>
            )}

            <div className="grid gap-2">
                <Label id="criticidad-label">Criticidad</Label>
                <input
                    type="hidden"
                    name="criticidad"
                    value={criticidad ?? ''}
                />
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={criticidad ?? ''}
                    onValueChange={(value) =>
                        setCriticidad(
                            (value || undefined) as
                                | CriticidadEquipo
                                | undefined,
                        )
                    }
                    className="w-full"
                    aria-labelledby="criticidad-label"
                    data-test="equipo-criticidad-toggle"
                >
                    {criticidadesEquipo.map((opcion) => (
                        <ToggleGroupItem
                            key={opcion.value}
                            value={opcion.value}
                            className={cn(
                                'grow basis-0',
                                COLOR_TOGGLE_CRITICIDAD[opcion.value],
                            )}
                        >
                            {opcion.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <InputError message={errors.criticidad} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cliente_id">Cliente</Label>
                <Combobox
                    id="cliente_id"
                    name="cliente_id"
                    value={clienteId}
                    onValueChange={setClienteId}
                    options={options.clientes.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder="Selecciona un cliente"
                    searchPlaceholder="Buscar cliente..."
                />
                <InputError message={errors.cliente_id} />
            </div>

            {isCreate ? (
                <>
                    <input type="hidden" name="activo" value="1" />
                    <input
                        type="hidden"
                        name="requiere_programacion"
                        value="1"
                    />
                </>
            ) : (
                <div className="flex flex-col gap-4 sm:col-span-2 sm:flex-row sm:items-center sm:gap-8">
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="activo"
                            name="activo"
                            defaultChecked={equipo?.activo ?? true}
                        />
                        <Label htmlFor="activo">Activo</Label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="requiere_programacion"
                            name="requiere_programacion"
                            defaultChecked={
                                equipo?.requiere_programacion ?? true
                            }
                        />
                        <Label htmlFor="requiere_programacion">
                            Requiere programación
                        </Label>
                    </div>
                </div>
            )}
        </div>
    );
}
