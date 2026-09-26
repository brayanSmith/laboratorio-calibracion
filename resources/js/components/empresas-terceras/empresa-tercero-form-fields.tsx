import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EmpresaTercero } from '@/types';

type Props = {
    empresaTercero?: EmpresaTercero;
    errors: Partial<Record<string, string>>;
};

export default function EmpresaTerceroFormFields({
    empresaTercero,
    errors,
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="nit">NIT</Label>
                <Input
                    id="nit"
                    name="nit"
                    defaultValue={empresaTercero?.nit}
                    placeholder="900123456-7"
                    required
                />
                <InputError message={errors.nit} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={empresaTercero?.nombre}
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={empresaTercero?.direccion ?? ''}
                />
                <InputError message={errors.direccion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    name="telefono"
                    defaultValue={empresaTercero?.telefono ?? ''}
                />
                <InputError message={errors.telefono} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={empresaTercero?.email ?? ''}
                />
                <InputError message={errors.email} />
            </div>
        </div>
    );
}
