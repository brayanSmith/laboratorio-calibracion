import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Area } from '@/types';

type Props = {
    area?: Area;
    errors: Partial<Record<string, string>>;
};

export default function AreaFormFields({ area, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={area?.nombre}
                    placeholder="Laboratorio de presión"
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Input
                    id="descripcion"
                    name="descripcion"
                    defaultValue={area?.descripcion ?? ''}
                />
                <InputError message={errors.descripcion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={area?.direccion ?? ''}
                />
                <InputError message={errors.direccion} />
            </div>
        </div>
    );
}
