import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermissionCatalogGroup, RoleDetail } from '@/types';

type Props = {
    role?: RoleDetail;
    catalog: PermissionCatalogGroup[];
    errors: Partial<Record<string, string>>;
};

export default function RoleFormFields({ role, catalog, errors }: Props) {
    const [selected, setSelected] = useState<string[]>(role?.permissions ?? []);

    const toggle = (permission: string, checked: boolean) => {
        setSelected((current) =>
            checked
                ? [...current, permission]
                : current.filter((value) => value !== permission),
        );
    };

    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre del rol</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={role?.name}
                    placeholder="Supervisor de calidad"
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-3">
                <Label>Permisos</Label>

                <div className="grid gap-4 sm:grid-cols-2">
                    {catalog.map((group) => (
                        <fieldset
                            key={group.group}
                            className="space-y-3 rounded-lg border p-4"
                        >
                            <legend className="px-1 text-sm font-medium">
                                {group.group}
                            </legend>

                            {group.permissions.map((permission) => (
                                <div
                                    key={permission.value}
                                    className="flex items-center gap-3"
                                >
                                    <Checkbox
                                        id={permission.value}
                                        checked={selected.includes(
                                            permission.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                permission.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor={permission.value}>
                                        {permission.label}
                                    </Label>
                                </div>
                            ))}
                        </fieldset>
                    ))}
                </div>

                {selected.map((permission) => (
                    <input
                        key={permission}
                        type="hidden"
                        name="permissions[]"
                        value={permission}
                    />
                ))}

                <InputError message={errors.permissions} />
            </div>
        </div>
    );
}
