import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EquipoEspecificacionTecnica, EquipoFormOptions } from '@/types';

type Props = {
    especificacion?: EquipoEspecificacionTecnica | null;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
    required?: boolean;
};

type PrecisionSimbolo = '±' | '%';

/**
 * Split a stored precision value ("±0.5" or "0.5%") into its number and symbol.
 */
function parsePrecision(value?: string | null): {
    numero: string;
    simbolo: PrecisionSimbolo;
} {
    if (value?.startsWith('±')) {
        return { numero: value.slice(1), simbolo: '±' };
    }

    if (value?.endsWith('%')) {
        return { numero: value.slice(0, -1), simbolo: '%' };
    }

    return { numero: value ?? '', simbolo: '±' };
}

/**
 * Strip the unidad de medida's símbolo from the end of a stored value
 * (e.g. "0.01mm" with símbolo "mm" becomes "0.01").
 */
function parseValorConSimbolo(
    value: string | null | undefined,
    simbolo?: string,
): string {
    if (!value) {
        return '';
    }

    if (simbolo && value.endsWith(simbolo)) {
        return value.slice(0, -simbolo.length);
    }

    return value;
}

function PrecisionSimboloSelect({
    value,
    onChange,
}: {
    value: PrecisionSimbolo;
    onChange: (value: PrecisionSimbolo) => void;
}) {
    return (
        <Select
            value={value}
            onValueChange={(nuevo) => onChange(nuevo as PrecisionSimbolo)}
        >
            <SelectTrigger className="w-16" aria-label="Símbolo de precisión">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="±">±</SelectItem>
                <SelectItem value="%">%</SelectItem>
            </SelectContent>
        </Select>
    );
}

export default function EquipoEspecificacionTecnicaFields({
    especificacion,
    options,
    errors,
    required = false,
}: Props) {
    const inicial = parsePrecision(especificacion?.precision);
    const [precisionNumero, setPrecisionNumero] = useState(inicial.numero);
    const [precisionSimbolo, setPrecisionSimbolo] = useState<PrecisionSimbolo>(
        inicial.simbolo,
    );

    // El % va al final del valor ("0.5%") y el ± al principio ("±0.5").
    const precisionValor = precisionNumero
        ? precisionSimbolo === '%'
            ? `${precisionNumero}%`
            : `±${precisionNumero}`
        : '';

    const [tipoMagnitudId, setTipoMagnitudId] = useState(
        especificacion?.tipo_magnitud_id?.toString(),
    );

    const [unidadMedidaId, setUnidadMedidaId] = useState(
        especificacion?.unidad_medida_id?.toString(),
    );
    const unidadSeleccionada = options.unidadesMedida.find(
        (option) => option.id.toString() === unidadMedidaId,
    );

    const [alcanceIndicacionTexto, setAlcanceIndicacionTexto] = useState(() =>
        parseValorConSimbolo(
            especificacion?.alcance_indicacion,
            unidadSeleccionada?.simbolo,
        ),
    );

    // El símbolo de la unidad de medida elegida se concatena al final ("0 a 100mm").
    const alcanceIndicacionValor = alcanceIndicacionTexto
        ? `${alcanceIndicacionTexto}${unidadSeleccionada?.simbolo ?? ''}`
        : '';

    const [resolucionNumero, setResolucionNumero] = useState(() =>
        parseValorConSimbolo(
            especificacion?.resolucion,
            unidadSeleccionada?.simbolo,
        ),
    );

    // El símbolo de la unidad de medida elegida se concatena al final ("0.01mm").
    const resolucionValor = resolucionNumero
        ? `${resolucionNumero}${unidadSeleccionada?.simbolo ?? ''}`
        : '';

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="tipo_magnitud_id">Tipo de magnitud</Label>
                <Combobox
                    id="tipo_magnitud_id"
                    name="tipo_magnitud_id"
                    value={tipoMagnitudId}
                    onValueChange={setTipoMagnitudId}
                    options={options.tiposMagnitud.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder="Selecciona un tipo de magnitud"
                    searchPlaceholder="Buscar tipo de magnitud..."
                />
                <InputError message={errors.tipo_magnitud_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="unidad_medida_id">Unidad de medida</Label>
                <Combobox
                    id="unidad_medida_id"
                    name="unidad_medida_id"
                    value={unidadMedidaId}
                    onValueChange={setUnidadMedidaId}
                    options={options.unidadesMedida.map((option) => ({
                        id: option.id,
                        label: `${option.nombre} (${option.simbolo})`,
                    }))}
                    placeholder="Selecciona una unidad de medida"
                    searchPlaceholder="Buscar unidad de medida..."
                />
                <InputError message={errors.unidad_medida_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alcance_indicacion_texto">
                    Alcance de indicación
                </Label>
                <div className="flex items-center gap-2">
                    <Input
                        id="alcance_indicacion_texto"
                        type="text"
                        value={alcanceIndicacionTexto}
                        onChange={(event) =>
                            setAlcanceIndicacionTexto(event.target.value)
                        }
                        required={required}
                        className="flex-1"
                    />
                    {unidadSeleccionada ? (
                        <span
                            className="text-sm text-muted-foreground"
                            aria-hidden="true"
                        >
                            {unidadSeleccionada.simbolo}
                        </span>
                    ) : null}
                </div>
                <input
                    type="hidden"
                    name="alcance_indicacion"
                    value={alcanceIndicacionValor}
                />
                <InputError message={errors.alcance_indicacion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="precision_numero">Precisión</Label>
                <div className="flex gap-2">
                    {precisionSimbolo === '±' ? (
                        <PrecisionSimboloSelect
                            value={precisionSimbolo}
                            onChange={setPrecisionSimbolo}
                        />
                    ) : null}
                    <Input
                        id="precision_numero"
                        type="number"
                        step="0.01"
                        value={precisionNumero}
                        onChange={(event) =>
                            setPrecisionNumero(event.target.value)
                        }
                        required={required}
                        className="flex-1"
                    />
                    {precisionSimbolo === '%' ? (
                        <PrecisionSimboloSelect
                            value={precisionSimbolo}
                            onChange={setPrecisionSimbolo}
                        />
                    ) : null}
                </div>
                <input type="hidden" name="precision" value={precisionValor} />
                <InputError message={errors.precision} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="resolucion_numero">Resolución</Label>
                <div className="flex items-center gap-2">
                    <Input
                        id="resolucion_numero"
                        type="number"
                        step="0.01"
                        value={resolucionNumero}
                        onChange={(event) =>
                            setResolucionNumero(event.target.value)
                        }
                        required={required}
                        className="flex-1"
                    />
                    {unidadSeleccionada ? (
                        <span
                            className="text-sm text-muted-foreground"
                            aria-hidden="true"
                        >
                            {unidadSeleccionada.simbolo}
                        </span>
                    ) : null}
                </div>
                <input
                    type="hidden"
                    name="resolucion"
                    value={resolucionValor}
                />
                <InputError message={errors.resolucion} />
            </div>
        </div>
    );
}
