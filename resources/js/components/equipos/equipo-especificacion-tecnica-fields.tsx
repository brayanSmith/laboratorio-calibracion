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

export default function EquipoEspecificacionTecnicaFields({
    especificacion,
    options,
    errors,
    required = false,
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="tipo_magnitud_id">Tipo de magnitud</Label>
                <Select
                    name="tipo_magnitud_id"
                    defaultValue={especificacion?.tipo_magnitud_id.toString()}
                >
                    <SelectTrigger id="tipo_magnitud_id" className="w-full">
                        <SelectValue placeholder="Selecciona un tipo de magnitud" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.tiposMagnitud.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_magnitud_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="unidad_medida_id">Unidad de medida</Label>
                <Select
                    name="unidad_medida_id"
                    defaultValue={especificacion?.unidad_medida_id.toString()}
                >
                    <SelectTrigger id="unidad_medida_id" className="w-full">
                        <SelectValue placeholder="Selecciona una unidad de medida" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.unidadesMedida.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={option.id.toString()}
                            >
                                {option.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.unidad_medida_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alcance_indicacion">
                    Alcance de indicación
                </Label>
                <Input
                    id="alcance_indicacion"
                    name="alcance_indicacion"
                    type="number"
                    step="0.01"
                    defaultValue={especificacion?.alcance_indicacion}
                    required={required}
                />
                <InputError message={errors.alcance_indicacion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="precision">Precisión</Label>
                <Input
                    id="precision"
                    name="precision"
                    type="number"
                    step="0.01"
                    defaultValue={especificacion?.precision}
                    required={required}
                />
                <InputError message={errors.precision} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="resolucion">Resolución</Label>
                <Input
                    id="resolucion"
                    name="resolucion"
                    type="number"
                    step="0.01"
                    defaultValue={especificacion?.resolucion}
                    required={required}
                />
                <InputError message={errors.resolucion} />
            </div>
        </div>
    );
}
