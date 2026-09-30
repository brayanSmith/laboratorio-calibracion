import InputError from '@/components/input-error';
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
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo?: Equipo;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
    variant?: 'create' | 'edit';
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
}: Props) {
    const isCreate = variant === 'create';

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
                <Select
                    name="tipo_equipo_id"
                    defaultValue={equipo?.tipo_equipo_id?.toString()}
                >
                    <SelectTrigger id="tipo_equipo_id" className="w-full">
                        <SelectValue placeholder="Selecciona un tipo de equipo" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.tipoEquipos.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_equipo_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_tecnologia">Tipo de tecnología</Label>
                <Select
                    name="tipo_tecnologia"
                    defaultValue={equipo?.tipo_tecnologia}
                >
                    <SelectTrigger id="tipo_tecnologia" className="w-full">
                        <SelectValue placeholder="Selecciona una tecnología" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="ANALOGICO">Analógico</SelectItem>
                        <SelectItem value="DIGITAL">Digital</SelectItem>
                    </SelectContent>
                </Select>
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
                <Select
                    name="fabricante_id"
                    defaultValue={equipo?.fabricante_id?.toString()}
                >
                    <SelectTrigger id="fabricante_id" className="w-full">
                        <SelectValue placeholder="Selecciona un fabricante" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.fabricantes.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
                <Select
                    name="cliente_id"
                    defaultValue={equipo?.cliente_id?.toString()}
                >
                    <SelectTrigger id="cliente_id" className="w-full">
                        <SelectValue placeholder="Selecciona un cliente" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.clientes.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
