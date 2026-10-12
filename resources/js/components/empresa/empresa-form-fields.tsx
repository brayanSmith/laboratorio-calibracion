import ColorField from '@/components/empresa/color-field';
import LogoField from '@/components/empresa/logo-field';
import { COLORES_NAV, COLORES_TEMA } from '@/lib/empresa-colors';
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

            <ColorField
                id="color_tema"
                name="color_tema"
                label="Color del tema"
                presets={COLORES_TEMA}
                description="Color de los botones y acentos de la interfaz."
                defaultValue={empresa?.color_tema}
                error={errors.color_tema}
            />

            <ColorField
                id="color_nav"
                name="color_nav"
                label="Color de la barra de navegación"
                presets={COLORES_NAV}
                description="Color del menú lateral."
                defaultValue={empresa?.color_nav}
                error={errors.color_nav}
            />
        </div>
    );
}
