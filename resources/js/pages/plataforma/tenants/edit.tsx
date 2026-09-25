import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Plataforma/TenantController';
import Heading from '@/components/heading';
import DeleteTenantModal from '@/components/tenants/delete-tenant-modal';
import TenantFormFields from '@/components/tenants/tenant-form-fields';
import TenantUsers from '@/components/tenants/tenant-users';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/plataforma/tenants';
import type { Tenant, TenantUser } from '@/types';

type Props = {
    tenant: Tenant;
    users: TenantUser[];
};

export default function TenantsEdit({ tenant, users }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    return (
        <>
            <Head title={`Editar tenant ${tenant.nombre}`} />

            <h1 className="sr-only">Editar tenant</h1>

            <div className="max-w-xl space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={`Editar tenant ${tenant.nombre}`}
                        description="Actualiza la información del tenant"
                    />

                    <Button
                        variant="destructive"
                        size="sm"
                        data-test="tenant-delete-button"
                        onClick={() => setDeleteDialogOpen(true)}
                    >
                        Eliminar tenant
                    </Button>
                </div>

                <Form
                    {...TenantController.update.form(tenant.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TenantFormFields tenant={tenant} errors={errors} />

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="tenant-update-button"
                                >
                                    Guardar cambios
                                </Button>
                                <Button variant="secondary" asChild>
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="max-w-3xl border-t p-4 pt-6">
                <TenantUsers tenant={tenant} users={users} />
            </div>

            <DeleteTenantModal
                tenant={tenant}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

TenantsEdit.layout = (props: { tenant: Tenant }) => ({
    breadcrumbs: [
        { title: 'Tenants', href: index() },
        {
            title: `Editar ${props.tenant.nombre}`,
            href: edit(props.tenant.id),
        },
    ],
});
