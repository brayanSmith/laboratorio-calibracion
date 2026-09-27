import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TipoMagnitud } from '@/types';

type Props = {
    tipoMagnitud?: TipoMagnitud;
    errors: Partial<Record<string, string>>;
};

export default function TipoMagnitudFormFields({
    tipoMagnitud,
    errors,
}: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={tipoMagnitud?.nombre}
                    placeholder="Presión"
                    required
                />
                <InputError message={errors.nombre} />
            </div>
        </div>
    );
}
