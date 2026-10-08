import { useState } from 'react';
import CalibracionResultados from '@/components/calibraciones/calibracion-resultados';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    Calibracion,
    CalibracionAreaOption,
    CalibracionOption,
} from '@/types';

type Props = {
    calibracion: Calibracion;
    tecnicos: CalibracionOption[];
    laboratorios: CalibracionOption[];
    areas: CalibracionAreaOption[];
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
    const areaSeleccionada =
        areas.find((area) => area.id.toString() === solicitanteId) ?? null;

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            {/* Información del equipo */}
            <div className="space-y-2 rounded-md border p-3 sm:col-span-2">
                <p className="text-sm font-medium">
                    {calibracion.equipo.codigo} ·{' '}
                    {calibracion.equipo.tipo_equipo.nombre} ·{' '}
                    {calibracion.equipo.modelo}
                </p>
                {calibracion.equipo.cliente ? (
                    <p className="text-xs text-muted-foreground">
                        {calibracion.equipo.cliente.nombre}
                    </p>
                ) : null}

                <div className="grid grid-cols-2 gap-2 text-xs">
                    <p>
                        <span className="text-muted-foreground">
                            Fabricante:
                        </span>{' '}
                        {calibracion.equipo.fabricante.nombre}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            N° de serie:
                        </span>{' '}
                        {calibracion.equipo.numero_serie}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            Tecnología:
                        </span>{' '}
                        {calibracion.equipo.tipo_tecnologia}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            Tipo de mantenimiento:
                        </span>{' '}
                        {calibracion.equipo.tipo_equipo.tipo_mantenimiento}
                    </p>
                </div>

                {calibracion.equipo.ficha_tecnica ? (
                    <div className="grid grid-cols-2 gap-2 border-t pt-2 text-xs">
                        <p>
                            <span className="text-muted-foreground">País:</span>{' '}
                            {calibracion.equipo.ficha_tecnica.pais_procedencia}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                N° activo:
                            </span>{' '}
                            {calibracion.equipo.ficha_tecnica.numero_activo}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Proveedor:
                            </span>{' '}
                            {calibracion.equipo.ficha_tecnica.proveedor}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Costo USD:
                            </span>{' '}
                            {calibracion.equipo.ficha_tecnica.costo_usd}
                        </p>
                    </div>
                ) : null}
            </div>

            {/* Solicitante */}
            <div className="space-y-2 rounded-md border p-3 sm:col-span-2">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="solicitante_id">
                            Solicitante (opcional)
                        </Label>
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
                        <Label htmlFor="laboratorio_id">
                            Laboratorio (opcional)
                        </Label>
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
                </div>

                {areaSeleccionada ? (
                    <div className="grid gap-2 border-t pt-2 text-xs">
                        <p>
                            <span className="text-muted-foreground">
                                Dirección:
                            </span>{' '}
                            {areaSeleccionada.direccion ?? 'Sin dirección'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Descripción:
                            </span>{' '}
                            {areaSeleccionada.descripcion ?? 'Sin descripción'}
                        </p>
                    </div>
                ) : null}
            </div>

            {/* Fecha de la calibración + Técnico */}
            <div className="grid gap-2">
                <Label htmlFor="fecha_calibracion">
                    Fecha de la calibración
                </Label>
                <Input
                    id="fecha_calibracion"
                    name="fecha_calibracion"
                    type="date"
                    defaultValue={calibracion.fecha_calibracion.slice(0, 10)}
                    required
                />
                <InputError message={errors.fecha_calibracion} />
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

            {/* Condiciones ambientales */}
            <div className="grid grid-cols-2 gap-4 sm:col-span-2">
                <Label className="col-span-2">Condiciones ambientales</Label>
                <div className="grid gap-2">
                    <Label
                        htmlFor="temperatura"
                        className="text-xs text-muted-foreground"
                    >
                        Temperatura °C (opcional)
                    </Label>
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
                    <Label
                        htmlFor="humedad"
                        className="text-xs text-muted-foreground"
                    >
                        Humedad % (opcional)
                    </Label>
                    <Input
                        id="humedad"
                        name="humedad"
                        type="number"
                        step="0.01"
                        defaultValue={calibracion.humedad ?? ''}
                    />
                    <InputError message={errors.humedad} />
                </div>
            </div>

            {/* Método de calibración */}
            <div className="space-y-2 rounded-md border p-3 sm:col-span-2">
                <Label>Método de calibración</Label>
                <div className="grid gap-2">
                    <Label
                        htmlFor="procedimiento_id"
                        className="text-xs text-muted-foreground"
                    >
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
            </div>

            {/* Resultados de la calibración */}
            <div className="min-w-0 sm:col-span-2">
                <CalibracionResultados
                    detalles={calibracion.detalles_medicion}
                    errors={errors}
                />
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
        </div>
    );
}
