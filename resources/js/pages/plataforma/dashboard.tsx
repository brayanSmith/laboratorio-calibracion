import { Head, Link } from '@inertiajs/react';
import { Building2, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/plataforma';
import { index, show } from '@/routes/plataforma/tenants';
import type { PlatformStats } from '@/types';

function Stat({
    title,
    value,
    description,
    icon,
}: {
    title: string;
    value: number;
    description: string;
    icon: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardDescription>{title}</CardDescription>
                <span className="text-muted-foreground">{icon}</span>
            </CardHeader>
            <CardContent className="space-y-1">
                <CardTitle className="text-3xl">{value}</CardTitle>
                <p className="text-xs text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

export default function PlataformaDashboard({
    tenants,
    usuarios,
    recientes,
}: PlatformStats) {
    return (
        <>
            <Head title="Panel de plataforma" />

            <h1 className="sr-only">Panel de plataforma</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Panel de plataforma"
                    description="Resumen general de los laboratorios registrados"
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat
                        title="Tenants"
                        value={tenants.total}
                        description={`${tenants.activos} activos · ${tenants.inactivos} inactivos`}
                        icon={<Building2 className="size-4" />}
                    />
                    <Stat
                        title="Tenants activos"
                        value={tenants.activos}
                        description="Con acceso habilitado"
                        icon={<Building2 className="size-4" />}
                    />
                    <Stat
                        title="Usuarios"
                        value={usuarios.total}
                        description="En todos los laboratorios"
                        icon={<Users className="size-4" />}
                    />
                    <Stat
                        title="Pendientes de acceso"
                        value={usuarios.pendientes}
                        description="Aún no cambian su contraseña temporal"
                        icon={<Users className="size-4" />}
                    />
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Tenants recientes"
                            description="Los últimos laboratorios registrados"
                        />
                        <Button variant="outline" size="sm" asChild>
                            <Link href={index()}>Ver todos</Link>
                        </Button>
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Nombre
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Usuarios
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recientes.map((tenant) => (
                                    <tr
                                        key={tenant.id}
                                        data-test="tenant-reciente-row"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={show(tenant.id)}
                                                className="hover:underline"
                                            >
                                                {tenant.nombre}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            {tenant.users_count ?? 0}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    tenant.activo
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {tenant.activo
                                                    ? 'Activo'
                                                    : 'Inactivo'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}

                                {recientes.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Aún no hay tenants registrados.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

PlataformaDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Panel de plataforma',
            href: dashboard(),
        },
    ],
};
