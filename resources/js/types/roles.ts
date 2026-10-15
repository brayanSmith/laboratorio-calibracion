export type PermissionCatalogGroup = {
    group: string;
    permissions: { value: string; label: string; mandatory: boolean }[];
};

export type RoleSummary = {
    id: number;
    name: string;
    is_system: boolean;
    permissions: string[];
    users_count: number;
};

export type TenantRoleOption = {
    id: number;
    name: string;
};

export type Usuario = {
    id: number;
    name: string;
    email: string;
    must_change_password: boolean;
    role_id: number | null;
    role_name: string | null;
    can_manage: boolean;
};
