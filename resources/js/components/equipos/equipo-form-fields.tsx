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
import { Textarea } from '@/components/ui/textarea';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo?: Equipo;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
};

export default function EquipoFormFields({ equipo, options, errors }: Props) {
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
                <Label htmlFor="numero_serie">Número de serie</Label>
                <Input
                    id="numero_serie"
                    name="numero_serie"
                    defaultValue={equipo?.numero_serie}
                    required
                />
                <InputError message={errors.numero_serie} />
            </div>

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
                <Label htmlFor="area_id">Área</Label>
                <Select
                    name="area_id"
                    defaultValue={equipo?.area_id?.toString()}
                >
                    <SelectTrigger id="area_id" className="w-full">
                        <SelectValue placeholder="Selecciona un área" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.areas.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.area_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="bahia_id">Bahía</Label>
                <Select
                    name="bahia_id"
                    defaultValue={equipo?.bahia_id?.toString()}
                >
                    <SelectTrigger id="bahia_id" className="w-full">
                        <SelectValue placeholder="Selecciona una bahía" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.bahias.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.bahia_id} />
            </div>

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

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="notas">Notas</Label>
                <Textarea
                    id="notas"
                    name="notas"
                    defaultValue={equipo?.notas ?? ''}
                    rows={3}
                />
                <InputError message={errors.notas} />
            </div>

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
                        id="patron_referencia"
                        name="patron_referencia"
                        defaultChecked={equipo?.patron_referencia ?? false}
                    />
                    <Label htmlFor="patron_referencia">
                        Patrón de referencia
                    </Label>
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="requiere_programacion"
                        name="requiere_programacion"
                        defaultChecked={equipo?.requiere_programacion ?? true}
                    />
                    <Label htmlFor="requiere_programacion">
                        Requiere programación
                    </Label>
                </div>
            </div>
        </div>
    );
}
