import { Search } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermissionCatalogGroup } from '@/types';

type Props = {
    role?: { name: string; permissions: string[] };
    catalog: PermissionCatalogGroup[];
    errors: Partial<Record<string, string>>;
};

const normalize = (text: string) =>
    text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default function RoleFormFields({ role, catalog, errors }: Props) {
    const mandatory = catalog.flatMap((group) =>
        group.permissions
            .filter((permission) => permission.mandatory)
            .map((permission) => permission.value),
    );
    const [selected, setSelected] = useState<string[]>([
        ...new Set([...mandatory, ...(role?.permissions ?? [])]),
    ]);
    const [search, setSearch] = useState('');

    const term = normalize(search.trim());
    const visibleGroups = catalog.filter(
        (group) => term === '' || normalize(group.group).includes(term),
    );

    const toggle = (permission: string, checked: boolean) => {
        setSelected((current) =>
            checked
                ? [...new Set([...current, permission])]
                : current.filter((value) => value !== permission),
        );
    };

    const toggleGroup = (group: PermissionCatalogGroup, checked: boolean) => {
        const values = group.permissions.map((permission) => permission.value);

        setSelected((current) =>
            checked
                ? [...new Set([...current, ...values])]
                : current.filter(
                      (value) =>
                          !values.includes(value) || mandatory.includes(value),
                  ),
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

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                            }
                        }}
                        placeholder="Buscar módulo..."
                        className="pl-9"
                        data-test="role-permissions-search"
                    />
                </div>

                <div className="grid gap-4">
                    {visibleGroups.map((group) => {
                        const selectedInGroup = group.permissions.filter(
                            (permission) => selected.includes(permission.value),
                        ).length;
                        const allSelected =
                            selectedInGroup === group.permissions.length;

                        return (
                            <fieldset
                                key={group.group}
                                className="w-full space-y-3 rounded-lg border p-4"
                            >
                                <legend className="px-1 text-sm font-medium">
                                    {group.group}
                                </legend>

                                <div className="flex items-center gap-3 border-b pb-3">
                                    <Checkbox
                                        id={`group-${group.group}`}
                                        checked={
                                            allSelected
                                                ? true
                                                : selectedInGroup > 0
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleGroup(group, checked === true)
                                        }
                                        data-test="role-group-select-all"
                                    />
                                    <Label
                                        htmlFor={`group-${group.group}`}
                                        className="font-semibold"
                                    >
                                        Seleccionar todo
                                    </Label>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
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
                                                disabled={permission.mandatory}
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
                                </div>
                            </fieldset>
                        );
                    })}

                    {visibleGroups.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Ningún módulo coincide con la búsqueda.
                        </p>
                    ) : null}
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
