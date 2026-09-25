import { usePage } from '@inertiajs/react';

export function usePermissions(): { can: (permission: string) => boolean } {
    const { auth } = usePage().props;

    return {
        can: (permission) => auth.permissions.includes(permission),
    };
}
