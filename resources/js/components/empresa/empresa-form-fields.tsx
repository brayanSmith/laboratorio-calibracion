import ColorField from '@/components/empresa/color-field';
import LogoField from '@/components/empresa/logo-field';
import Heading from '@/components/heading';
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
                <Label htmlFor="nit">RUC</Label>
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

            <div className="sm:col-span-2">
                <Heading
                    variant="small"
                    title="Laboratorio propio"
                    description="Donde se hacen las calibraciones locales; su nombre sale como lugar de calibración en el certificado."
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="laboratorio_nombre">
                    Nombre del laboratorio
                </Label>
                <Input
                    id="laboratorio_nombre"
                    name="laboratorio_nombre"
                    defaultValue={empresa?.laboratorio_nombre ?? ''}
                />
                <InputError message={errors.laboratorio_nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="laboratorio_direccion">
                    Dirección del laboratorio
                </Label>
                <Input
                    id="laboratorio_direccion"
                    name="laboratorio_direccion"
                    defaultValue={empresa?.laboratorio_direccion ?? ''}
                />
                <InputError message={errors.laboratorio_direccion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="laboratorio_email">
                    Correo del laboratorio
                </Label>
                <Input
                    id="laboratorio_email"
                    name="laboratorio_email"
                    type="email"
                    defaultValue={empresa?.laboratorio_email ?? ''}
                />
                <InputError message={errors.laboratorio_email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="laboratorio_telefono">
                    Teléfono del laboratorio
                </Label>
                <Input
                    id="laboratorio_telefono"
                    name="laboratorio_telefono"
                    defaultValue={empresa?.laboratorio_telefono ?? ''}
                />
                <InputError message={errors.laboratorio_telefono} />
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
