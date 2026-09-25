import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TenantRoleOption } from '@/types';

type Props = {
    roles: TenantRoleOption[];
    defaultValue?: number | null;
    error?: string;
};

export default function RoleSelect({ roles, defaultValue, error }: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="role_id">Rol</Label>
            <Select name="role_id" defaultValue={defaultValue?.toString()}>
                <SelectTrigger id="role_id" className="w-full">
                    <SelectValue placeholder="Selecciona un rol" />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role.id} value={role.id.toString()}>
                            {role.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
