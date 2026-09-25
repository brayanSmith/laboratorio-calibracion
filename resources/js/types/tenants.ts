export type Tenant = {
    id: number;
    nombre: string;
    slug: string;
    activo: boolean;
    users_count?: number;
    created_at: string;
    updated_at: string;
};

export type PlatformStats = {
    tenants: {
        total: number;
        activos: number;
        inactivos: number;
    };
    usuarios: {
        total: number;
        pendientes: number;
    };
    recientes: Tenant[];
};

export type TenantUser = {
    id: number;
    name: string;
    email: string;
    must_change_password: boolean;
};

export type TenantPaginator = {
    data: Tenant[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
