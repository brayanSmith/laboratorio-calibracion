import { useState } from 'react';
import Combobox from '@/components/combobox';
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
import {
    estadosEquipoMantenimiento,
    estadosMantenimiento,
} from '@/lib/estados-mantenimiento';
import type { Mantenimiento, MantenimientoOption } from '@/types';

type Props = {
    mantenimiento: Mantenimiento;
    tecnicos: MantenimientoOption[];
    novedadesMantenimiento: MantenimientoOption[];
    errors: Partial<Record<string, string>>;
};

function toComboboxOptions(options: MantenimientoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

export default function MantenimientoFormFields({
    mantenimiento,
    tecnicos,
    novedadesMantenimiento,
    errors,
}: Props) {
    const [tecnicoId, setTecnicoId] = useState(
        mantenimiento.tecnico_id.toString(),
    );
    const [novedadId, setNovedadId] = useState(
        mantenimiento.novedad_id?.toString() ?? '',
    );

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label>Equipo</Label>
                <p className="text-sm text-muted-foreground">
                    {mantenimiento.orden_trabajo_codigo} ·{' '}
                    {mantenimiento.equipo.codigo} ·{' '}
                    {mantenimiento.equipo.tipo_equipo.nombre} ·{' '}
                    {mantenimiento.equipo.modelo}
                    {mantenimiento.equipo.cliente
                        ? ` · ${mantenimiento.equipo.cliente.nombre}`
                        : ''}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tecnico_id">Técnico</Label>
                <Combobox
                    id="tecnico_id"
                    name="tecnico_id"
                    value={tecnicoId}
                    onValueChange={setTecnicoId}
                    options={toComboboxOptions(tecnicos)}
                    placeholder="Selecciona un técnico"
                    searchPlaceholder="Buscar técnico..."
                />
                <InputError message={errors.tecnico_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="fecha_mantenimiento">
                    Fecha del mantenimiento
                </Label>
                <Input
                    id="fecha_mantenimiento"
                    name="fecha_mantenimiento"
                    type="date"
                    defaultValue={mantenimiento.fecha_mantenimiento.slice(
                        0,
                        10,
                    )}
                    required
                />
                <InputError message={errors.fecha_mantenimiento} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="estado_mantenimiento">
                    Estado del mantenimiento
                </Label>
                <Select
                    name="estado_mantenimiento"
                    defaultValue={mantenimiento.estado_mantenimiento}
                >
                    <SelectTrigger id="estado_mantenimiento" className="w-full">
                        <SelectValue placeholder="Selecciona un estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {estadosMantenimiento.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.estado_mantenimiento} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="novedad_id">Novedad (opcional)</Label>
                <Combobox
                    id="novedad_id"
                    name="novedad_id"
                    value={novedadId}
                    onValueChange={setNovedadId}
                    options={toComboboxOptions(novedadesMantenimiento)}
                    placeholder="Selecciona una novedad"
                    searchPlaceholder="Buscar novedad..."
                />
                <InputError message={errors.novedad_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="estado_inicial_equipo">
                    Estado inicial del equipo (opcional)
                </Label>
                <Select
                    name="estado_inicial_equipo"
                    defaultValue={
                        mantenimiento.estado_inicial_equipo ?? undefined
                    }
                >
                    <SelectTrigger
                        id="estado_inicial_equipo"
                        className="w-full"
                    >
                        <SelectValue placeholder="Selecciona un estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {estadosEquipoMantenimiento.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.estado_inicial_equipo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="estado_final_equipo">
                    Estado final del equipo (opcional)
                </Label>
                <Select
                    name="estado_final_equipo"
                    defaultValue={
                        mantenimiento.estado_final_equipo ?? undefined
                    }
                >
                    <SelectTrigger id="estado_final_equipo" className="w-full">
                        <SelectValue placeholder="Selecciona un estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {estadosEquipoMantenimiento.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.estado_final_equipo} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="descripcion">Descripción (opcional)</Label>
                <Textarea
                    id="descripcion"
                    name="descripcion"
                    defaultValue={mantenimiento.descripcion ?? ''}
                    placeholder="Describe el trabajo realizado"
                />
                <InputError message={errors.descripcion} />
            </div>

            <div className="flex items-center gap-2 sm:col-span-2">
                <Checkbox
                    id="firmado"
                    name="firmado"
                    defaultChecked={mantenimiento.firmado}
                />
                <Label htmlFor="firmado" className="font-normal">
                    Firmado
                </Label>
                <InputError message={errors.firmado} />
            </div>
        </div>
    );
}
