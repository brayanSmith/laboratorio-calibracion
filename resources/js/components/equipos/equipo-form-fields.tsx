import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo?: Equipo;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
    variant?: 'create' | 'edit';
};

const OPCIONES_TECNOLOGIA = [
    { id: 'ANALOGICO', label: 'Analógico' },
    { id: 'DIGITAL', label: 'Digital' },
];

/**
 * Datos generales del equipo. The caller is responsible for its own
 * heading; move this component around freely to reposition the section.
 */
export default function EquipoFormFields({
    equipo,
    options,
    errors,
    variant = 'edit',
}: Props) {
    const isCreate = variant === 'create';

    const [tipoEquipoId, setTipoEquipoId] = useState(
        equipo?.tipo_equipo_id?.toString(),
    );
    const [tipoTecnologia, setTipoTecnologia] = useState(
        equipo?.tipo_tecnologia,
    );
    const [fabricanteId, setFabricanteId] = useState(
        equipo?.fabricante_id?.toString(),
    );
    const [clienteId, setClienteId] = useState(equipo?.cliente_id?.toString());

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="codigo">Código</Label>
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
                    onValueChange={setTipoEquipoId}
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
                <Label htmlFor="tipo_tecnologia">Tipo de tecnología</Label>
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

            {!isCreate ? (
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="concatenar_codigo_nombre">
                        Concatenar código y nombre
                    </Label>
                    <Input
                        id="concatenar_codigo_nombre"
                        name="concatenar_codigo_nombre"
                        defaultValue={equipo?.concatenar_codigo_nombre ?? ''}
                    />
                    <InputError message={errors.concatenar_codigo_nombre} />
                </div>
            ) : null}

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
