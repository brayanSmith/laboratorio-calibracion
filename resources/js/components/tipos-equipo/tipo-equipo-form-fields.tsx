import InputError from '@/components/input-error';
import { tiposMantenimiento } from '@/components/tipos-equipo/tipo-mantenimiento';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TipoEquipo } from '@/types';

type Props = {
    tipoEquipo?: TipoEquipo;
    errors: Partial<Record<string, string>>;
};

export default function TipoEquipoFormFields({ tipoEquipo, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={tipoEquipo?.nombre}
                    placeholder="Manómetro"
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_mantenimiento">
                    Tipo de mantenimiento
                </Label>
                <Select
                    name="tipo_mantenimiento"
                    defaultValue={tipoEquipo?.tipo_mantenimiento}
                >
                    <SelectTrigger id="tipo_mantenimiento" className="w-full">
                        <SelectValue placeholder="Selecciona un tipo" />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposMantenimiento.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_mantenimiento} />
            </div>
        </div>
    );
}
