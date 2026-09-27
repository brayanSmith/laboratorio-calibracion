import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Cliente } from '@/types';

type Props = {
    cliente?: Cliente;
    errors: Partial<Record<string, string>>;
};

export default function ClienteFormFields({ cliente, errors }: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={cliente?.nombre}
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={cliente?.email}
                    required
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    name="telefono"
                    defaultValue={cliente?.telefono ?? ''}
                />
                <InputError message={errors.telefono} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={cliente?.direccion ?? ''}
                />
                <InputError message={errors.direccion} />
            </div>
        </div>
    );
}
