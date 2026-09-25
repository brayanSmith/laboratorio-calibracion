import { useState } from 'react';
import InputError from '@/components/input-error';
import TemporaryPasswordField from '@/components/tenants/temporary-password-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Tenant } from '@/types';

type Props = {
    tenant?: Tenant;
    errors: Partial<Record<string, string>>;
    includeAdmin?: boolean;
};

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function TenantFormFields({
    tenant,
    errors,
    includeAdmin = false,
}: Props) {
    const [nombre, setNombre] = useState(tenant?.nombre ?? '');
    const [slug, setSlug] = useState(tenant?.slug ?? '');
    const [slugEditadoManualmente, setSlugEditadoManualmente] = useState(
        tenant ? tenant.slug !== slugify(tenant.nombre) : false,
    );

    const handleNombreChange = (value: string) => {
        setNombre(value);

        if (!slugEditadoManualmente) {
            setSlug(slugify(value));
        }
    };

    const handleSlugChange = (value: string) => {
        setSlug(value);
        setSlugEditadoManualmente(value !== '' && value !== slugify(nombre));
    };

    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    value={nombre}
                    onChange={(event) => handleNombreChange(event.target.value)}
                    placeholder="Laboratorio Metrológico S.A."
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">Slug</Label>
                <Input
                    id="slug"
                    name="slug"
                    value={slug}
                    onChange={(event) => handleSlugChange(event.target.value)}
                    placeholder="laboratorio-metrologico-sa"
                />
                <p className="text-xs text-muted-foreground">
                    Se genera automáticamente a partir del nombre; puedes
                    editarlo si necesitas otro.
                </p>
                <InputError message={errors.slug} />
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id="activo"
                    name="activo"
                    defaultChecked={tenant?.activo ?? true}
                />
                <Label htmlFor="activo">Activo</Label>
            </div>

            {includeAdmin ? (
                <div className="grid gap-6 border-t pt-6">
                    <div className="grid gap-1">
                        <h3 className="text-sm font-medium">
                            Administrador del tenant
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Usuario principal del laboratorio. Podrás corregir
                            su correo o regenerar su contraseña al editar el
                            tenant.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_name">Nombre</Label>
                        <Input
                            id="admin_name"
                            name="admin_name"
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.admin_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_email">Correo electrónico</Label>
                        <Input
                            id="admin_email"
                            name="admin_email"
                            type="email"
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.admin_email} />
                    </div>

                    <TemporaryPasswordField
                        id="admin_password"
                        name="admin_password"
                        error={errors.admin_password}
                    />
                </div>
            ) : null}
        </div>
    );
}
