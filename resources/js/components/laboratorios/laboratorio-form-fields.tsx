import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Laboratorio } from '@/types';

type Props = {
    laboratorio?: Laboratorio;
    errors: Partial<Record<string, string>>;
};

export default function LaboratorioFormFields({ laboratorio, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={laboratorio?.nombre}
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
                    defaultValue={laboratorio?.descripcion ?? ''}
                />
                <InputError message={errors.descripcion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={laboratorio?.direccion ?? ''}
                />
                <InputError message={errors.direccion} />
            </div>
        </div>
    );
}
