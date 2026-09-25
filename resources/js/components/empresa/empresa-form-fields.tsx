import LogoField from '@/components/empresa/logo-field';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Empresa } from '@/types';

type Props = {
    empresa?: Empresa;
    errors: Partial<Record<string, string>>;
};

export default function EmpresaFormFields({ empresa, errors }: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="nit">NIT</Label>
                <Input
                    id="nit"
                    name="nit"
                    defaultValue={empresa?.nit}
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
                    defaultValue={empresa?.nombre}
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={empresa?.direccion}
                    required
                />
                <InputError message={errors.direccion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    name="telefono"
                    defaultValue={empresa?.telefono}
                    required
                />
                <InputError message={errors.telefono} />
            </div>

            <LogoField empresa={empresa} error={errors.logo} />
        </div>
    );
}
