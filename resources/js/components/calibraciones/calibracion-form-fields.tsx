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
import { estadosCalibracion } from '@/lib/estados-calibracion';
import type { Calibracion, CalibracionOption } from '@/types';

type Props = {
    calibracion: Calibracion;
    tecnicos: CalibracionOption[];
    laboratorios: CalibracionOption[];
    areas: CalibracionOption[];
    procedimientos: CalibracionOption[];
    novedadesCalibracion: CalibracionOption[];
    errors: Partial<Record<string, string>>;
};

function toComboboxOptions(options: CalibracionOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

export default function CalibracionFormFields({
    calibracion,
    tecnicos,
    laboratorios,
    areas,
    procedimientos,
    novedadesCalibracion,
    errors,
}: Props) {
    const [tecnicoId, setTecnicoId] = useState(
        calibracion.tecnico_id.toString(),
    );
    const [laboratorioId, setLaboratorioId] = useState(
        calibracion.laboratorio_id?.toString() ?? '',
    );
    const [solicitanteId, setSolicitanteId] = useState(
        calibracion.solicitante_id?.toString() ?? '',
    );
    const [procedimientoId, setProcedimientoId] = useState(
        calibracion.procedimiento_id?.toString() ?? '',
    );
    const [novedadId, setNovedadId] = useState(
        calibracion.novedad_id?.toString() ?? '',
    );

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label>Equipo</Label>
                <p className="text-sm text-muted-foreground">
                    {calibracion.orden_trabajo_codigo} ·{' '}
                    {calibracion.equipo.codigo} ·{' '}
                    {calibracion.equipo.tipo_equipo.nombre} ·{' '}
                    {calibracion.equipo.modelo}
                    {calibracion.equipo.cliente
                        ? ` · ${calibracion.equipo.cliente.nombre}`
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
                <Label htmlFor="estado_calibracion">
                    Estado de la calibración
                </Label>
                <Select
                    name="estado_calibracion"
                    defaultValue={calibracion.estado_calibracion}
                >
                    <SelectTrigger id="estado_calibracion" className="w-full">
                        <SelectValue placeholder="Selecciona un estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {estadosCalibracion.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.estado_calibracion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="laboratorio_id">Laboratorio (opcional)</Label>
                <Combobox
                    id="laboratorio_id"
                    name="laboratorio_id"
                    value={laboratorioId}
                    onValueChange={setLaboratorioId}
                    options={toComboboxOptions(laboratorios)}
                    placeholder="Selecciona un laboratorio"
                    searchPlaceholder="Buscar laboratorio..."
                />
                <InputError message={errors.laboratorio_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="solicitante_id">Solicitante (opcional)</Label>
                <Combobox
                    id="solicitante_id"
                    name="solicitante_id"
                    value={solicitanteId}
                    onValueChange={setSolicitanteId}
                    options={toComboboxOptions(areas)}
                    placeholder="Selecciona un área"
                    searchPlaceholder="Buscar área..."
                />
                <InputError message={errors.solicitante_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="procedimiento_id">
                    Procedimiento (opcional)
                </Label>
                <Combobox
                    id="procedimiento_id"
                    name="procedimiento_id"
                    value={procedimientoId}
                    onValueChange={setProcedimientoId}
                    options={toComboboxOptions(procedimientos)}
                    placeholder="Selecciona un procedimiento"
                    searchPlaceholder="Buscar procedimiento..."
                />
                <InputError message={errors.procedimiento_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="novedad_id">Novedad (opcional)</Label>
                <Combobox
                    id="novedad_id"
                    name="novedad_id"
                    value={novedadId}
                    onValueChange={setNovedadId}
                    options={toComboboxOptions(novedadesCalibracion)}
                    placeholder="Selecciona una novedad"
                    searchPlaceholder="Buscar novedad..."
                />
                <InputError message={errors.novedad_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="temperatura">Temperatura °C (opcional)</Label>
                <Input
                    id="temperatura"
                    name="temperatura"
                    type="number"
                    step="0.01"
                    defaultValue={calibracion.temperatura ?? ''}
                />
                <InputError message={errors.temperatura} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="humedad">Humedad % (opcional)</Label>
                <Input
                    id="humedad"
                    name="humedad"
                    type="number"
                    step="0.01"
                    defaultValue={calibracion.humedad ?? ''}
                />
                <InputError message={errors.humedad} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="ajustes_requeridos"
                    name="ajustes_requeridos"
                    defaultChecked={calibracion.ajustes_requeridos}
                />
                <Label htmlFor="ajustes_requeridos" className="font-normal">
                    Ajustes requeridos
                </Label>
                <InputError message={errors.ajustes_requeridos} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="firmado"
                    name="firmado"
                    defaultChecked={calibracion.firmado}
                />
                <Label htmlFor="firmado" className="font-normal">
                    Firmado
                </Label>
                <InputError message={errors.firmado} />
            </div>
        </div>
    );
}
