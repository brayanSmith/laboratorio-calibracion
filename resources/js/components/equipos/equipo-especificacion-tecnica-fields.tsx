import { useEffect, useState } from 'react';
import type { KeyboardEvent } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Toggle } from '@/components/ui/toggle';
import type { EquipoEspecificacionTecnica, EquipoFormOptions } from '@/types';

type Props = {
    especificacion?: EquipoEspecificacionTecnica | null;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
    required?: boolean;
    /** Avisa al padre cuando cambia el alcance de indicación (con el símbolo de la unidad). */
    onAlcanceIndicacionChange?: (alcanceIndicacion: string) => void;
};

/**
 * Split a stored precision value ("±0.5mm" or "±0.5%") into its number and whether it's a
 * porcentaje. The ± always goes in front; at the end goes the % when it's a porcentaje, or
 * the unidad de medida's símbolo otherwise.
 */
export function parsePrecision(value?: string | null): {
    numero: string;
    esPorcentaje: boolean;
} {
    const esPorcentaje = value?.endsWith('%') ?? false;

    return {
        numero: (value ?? '').replace(/^±/, '').match(/^\d*\.?\d*/)?.[0] ?? '',
        esPorcentaje,
    };
}

/**
 * "0.5" -> "±0.5%" if it's a porcentaje, or "±0.5mm" with the unidad's símbolo otherwise;
 * empty while there's no number.
 */
export function formatearPrecision(
    numero: string,
    esPorcentaje: boolean,
    simboloUnidad = '',
): string {
    return numero ? `±${numero}${esPorcentaje ? '%' : simboloUnidad}` : '';
}

/**
 * Split a stored alcance de indicación ("-10 a 100mm") into its alcance inicial and final.
 * Values that don't follow that format come back empty so they're entered again.
 */
export function parseAlcanceIndicacion(value?: string | null): {
    inicial: string;
    final: string;
} {
    const partes = (value ?? '').match(/^(-?\d+(?:\.\d+)?) a (\d+(?:\.\d+)?)/);

    return { inicial: partes?.[1] ?? '', final: partes?.[2] ?? '' };
}

/** "-10" and "100" -> "-10 a 100mm"; empty until both numbers are filled. */
export function formatearAlcanceIndicacion(
    inicial: string,
    final: string,
    simboloUnidad = '',
): string {
    return inicial && final ? `${inicial} a ${final}${simboloUnidad}` : '';
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

/** Number inputs accept "e" and "+" (1e5); the alcance only takes plain numbers. */
function bloquearNotacionCientifica(
    event: KeyboardEvent<HTMLInputElement>,
): void {
    if (['e', 'E', '+'].includes(event.key)) {
        event.preventDefault();
    }
}

export default function EquipoEspecificacionTecnicaFields({
    especificacion,
    options,
    errors,
    required = false,
    onAlcanceIndicacionChange,
}: Props) {
    const inicial = parsePrecision(especificacion?.precision);
    const [precisionNumero, setPrecisionNumero] = useState(inicial.numero);
    const [precisionPorcentaje, setPrecisionPorcentaje] = useState(
        inicial.esPorcentaje,
    );

    const [tipoMagnitudId, setTipoMagnitudId] = useState(
        especificacion?.tipo_magnitud_id?.toString(),
    );

    const [unidadMedidaId, setUnidadMedidaId] = useState(
        especificacion?.unidad_medida_id?.toString(),
    );
    const unidadSeleccionada = options.unidadesMedida.find(
        (option) => option.id.toString() === unidadMedidaId,
    );

    // El ± va siempre al principio; al final el % si se marca ("±0.5%") o, si no, el
    // símbolo de la unidad de medida elegida ("±0.5mm").
    const precisionValor = formatearPrecision(
        precisionNumero,
        precisionPorcentaje,
        unidadSeleccionada?.simbolo,
    );

    const [alcanceInicial, setAlcanceInicial] = useState(
        () =>
            parseAlcanceIndicacion(especificacion?.alcance_indicacion).inicial,
    );
    const [alcanceFinal, setAlcanceFinal] = useState(
        () => parseAlcanceIndicacion(especificacion?.alcance_indicacion).final,
    );

    // "[inicial] a [final]" con el símbolo de la unidad de medida elegida al final ("0 a 100mm").
    const alcanceIndicacionValor = formatearAlcanceIndicacion(
        alcanceInicial,
        alcanceFinal,
        unidadSeleccionada?.simbolo,
    );

    // Depende del texto y de la unidad elegida, así que se avisa cuando cambia el resultado.
    useEffect(() => {
        onAlcanceIndicacionChange?.(alcanceIndicacionValor);
    }, [alcanceIndicacionValor, onAlcanceIndicacionChange]);

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
                <Label htmlFor="alcance_inicial">Alcance de indicación</Label>
                <div className="flex items-center gap-2">
                    <Input
                        id="alcance_inicial"
                        type="number"
                        step="any"
                        value={alcanceInicial}
                        onChange={(event) =>
                            setAlcanceInicial(event.target.value)
                        }
                        onKeyDown={bloquearNotacionCientifica}
                        placeholder="Inicial"
                        aria-label="Alcance inicial"
                        required={required || alcanceFinal !== ''}
                        className="flex-1"
                    />
                    <span
                        className="text-sm text-muted-foreground"
                        aria-hidden="true"
                    >
                        a
                    </span>
                    <Input
                        id="alcance_final"
                        type="number"
                        step="any"
                        min="0"
                        value={alcanceFinal}
                        onChange={(event) =>
                            setAlcanceFinal(event.target.value)
                        }
                        onKeyDown={bloquearNotacionCientifica}
                        placeholder="Final"
                        aria-label="Alcance final"
                        required={required || alcanceInicial !== ''}
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
                <div className="flex items-center gap-2">
                    <span
                        className="text-sm text-muted-foreground"
                        aria-hidden="true"
                    >
                        ±
                    </span>
                    <Input
                        id="precision_numero"
                        type="number"
                        step="0.01"
                        min="0"
                        value={precisionNumero}
                        onChange={(event) =>
                            setPrecisionNumero(event.target.value)
                        }
                        required={required}
                        className="flex-1"
                    />
                    {!precisionPorcentaje && unidadSeleccionada ? (
                        <span
                            className="text-sm text-muted-foreground"
                            aria-hidden="true"
                        >
                            {unidadSeleccionada.simbolo}
                        </span>
                    ) : null}
                    <Toggle
                        variant="outline"
                        pressed={precisionPorcentaje}
                        onPressedChange={setPrecisionPorcentaje}
                        aria-label="La precisión es un porcentaje"
                        title="Marcar si la precisión es un porcentaje"
                        className="w-10 data-[state=on]:!bg-primary data-[state=on]:!text-primary-foreground"
                        data-test="precision-porcentaje"
                    >
                        %
                    </Toggle>
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
