import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
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

            <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={laboratorio?.email ?? ''}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    name="telefono"
                    type="tel"
                    defaultValue={laboratorio?.telefono ?? ''}
                />
                <InputError message={errors.telefono} />
            </div>

            <div className="grid gap-1">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="principal"
                        name="principal"
                        defaultChecked={laboratorio?.principal}
                    />
                    <Label htmlFor="principal" className="font-normal">
                        Laboratorio principal
                    </Label>
                </div>
                <p className="text-xs text-muted-foreground">
                    Se usa en las calibraciones locales. Solo uno puede ser el
                    principal: al marcar este se desmarca el anterior.
                </p>
                <InputError message={errors.principal} />
            </div>
        </div>
    );
}
