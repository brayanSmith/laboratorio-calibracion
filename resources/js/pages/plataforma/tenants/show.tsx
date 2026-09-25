import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/plataforma/tenants';
import type { Tenant } from '@/types';

type Props = {
    tenant: Tenant;
};

function Field({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-1">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">{value ?? '—'}</span>
        </div>
    );
}

export default function TenantsShow({ tenant }: Props) {
    return (
        <>
            <Head title={`Tenant ${tenant.nombre}`} />

            <h1 className="sr-only">Tenant {tenant.nombre}</h1>

            <div className="max-w-xl space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={`Tenant ${tenant.nombre}`}
                        description={tenant.slug}
                    />

                    <Button asChild data-test="tenant-edit-button">
                        <Link href={edit(tenant.id)}>
                            <Pencil /> Editar
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 rounded-lg border p-6 sm:grid-cols-2">
                    <Field label="Nombre" value={tenant.nombre} />
                    <Field label="Slug" value={tenant.slug} />
                    <Field label="Usuarios" value={tenant.users_count ?? 0} />
                    <Field
                        label="Estado"
                        value={
                            <Badge
                                variant={
                                    tenant.activo ? 'default' : 'secondary'
                                }
                            >
                                {tenant.activo ? 'Activo' : 'Inactivo'}
                            </Badge>
                        }
                    />
                </div>
            </div>
        </>
    );
}

TenantsShow.layout = (props: { tenant: Tenant }) => ({
    breadcrumbs: [
        { title: 'Tenants', href: index() },
        { title: props.tenant.nombre, href: show(props.tenant.id) },
    ],
});
