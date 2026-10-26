import { useState } from 'react';
import CalibracionResultados from '@/components/calibraciones/calibracion-resultados';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ValoresInstrumento } from '@/lib/calibracion-resultado';
import type { Calibracion, CalibracionOption } from '@/types';

type Props = {
    calibracion: Calibracion;
    laboratorioPropio: string | null;
    valoresInstrumento: ValoresInstrumento;
    onValorInstrumentoChange: (detalleId: number, valor: string) => void;
    procedimientos: CalibracionOption[];
    novedadesCalibracion: CalibracionOption[];
    errors: Partial<Record<string, string>>;
};

function toComboboxOptions(options: CalibracionOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/** Dato que solo se muestra: no es un campo del formulario y no se puede cambiar. */
function DatoDetalle({
    etiqueta,
    valor,
    vacio = '—',
    testId,
}: {
    etiqueta: string;
    valor?: string | null;
    vacio?: string;
    testId: string;
}) {
    return (
        <div className="grid gap-1">
            <Label className="text-xs text-muted-foreground">{etiqueta}</Label>
            <p className="text-sm" data-test={testId}>
                {valor || vacio}
            </p>
        </div>
    );
}

export default function CalibracionFormFields({
    calibracion,
    laboratorioPropio,
    valoresInstrumento,
    onValorInstrumentoChange,
    procedimientos,
    novedadesCalibracion,
    errors,
}: Props) {
    const [procedimientoId, setProcedimientoId] = useState(
        calibracion.procedimiento_id?.toString() ?? '',
    );
    const [novedadId, setNovedadId] = useState(
        calibracion.novedad_id?.toString() ?? '',
    );

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
                            Indicación:
                        </span>{' '}
                        {calibracion.equipo.tipo_tecnologia === 'DIGITAL'
                            ? 'Digital'
                            : 'Analógico'}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            Tipo de servicio:
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

            {/* Solicitante y laboratorio: solo se muestran, no se editan */}
            <div className="space-y-2 rounded-md border p-3 sm:col-span-2">
                <div className="grid gap-4 sm:grid-cols-2">
                    <DatoDetalle
                        etiqueta="Solicitante"
                        valor={calibracion.equipo.area?.etiqueta}
                        vacio="El equipo no tiene un área asignada"
                        testId="calibracion-solicitante"
                    />
                    <DatoDetalle
                        etiqueta="Laboratorio"
                        valor={
                            laboratorioPropio ?? calibracion.laboratorio_nombre
                        }
                        vacio="No hay un laboratorio propio configurado en Empresa"
                        testId="calibracion-laboratorio"
                    />
                </div>

                {calibracion.equipo.area ? (
                    <div className="grid gap-2 border-t pt-2 text-xs">
                        <p>
                            <span className="text-muted-foreground">
                                Dirección:
                            </span>{' '}
                            {calibracion.equipo.area.direccion ??
                                'Sin dirección'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Descripción:
                            </span>{' '}
                            {calibracion.equipo.area.descripcion ??
                                'Sin descripción'}
                        </p>
                    </div>
                ) : null}
            </div>

            {/* Fecha de la calibración + Técnico: solo se muestran, no se editan */}
            <DatoDetalle
                etiqueta="Fecha de la calibración"
                valor={calibracion.fecha_calibracion.slice(0, 10)}
                testId="calibracion-fecha"
            />
            <DatoDetalle
                etiqueta="Técnico"
                valor={calibracion.tecnico_nombre}
                testId="calibracion-tecnico"
            />

            {/* Condiciones ambientales */}
            <div className="grid grid-cols-2 gap-4 sm:col-span-2">
                <Label className="col-span-2">Condiciones ambientales</Label>
                <div className="grid gap-2">
                    <Label
                        htmlFor="temperatura"
                        className="text-xs text-muted-foreground"
                    >
                        Temperatura °C
                    </Label>
                    <Input
                        id="temperatura"
                        name="temperatura"
                        type="number"
                        step="0.01"
                        defaultValue={calibracion.temperatura ?? ''}
                        required
                    />
                    <InputError message={errors.temperatura} />
                </div>

                <div className="grid gap-2">
                    <Label
                        htmlFor="humedad"
                        className="text-xs text-muted-foreground"
                    >
                        Humedad %
                    </Label>
                    <Input
                        id="humedad"
                        name="humedad"
                        type="number"
                        step="0.01"
                        defaultValue={calibracion.humedad ?? ''}
                        required
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
                    valoresInstrumento={valoresInstrumento}
                    onValorInstrumentoChange={onValorInstrumentoChange}
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
