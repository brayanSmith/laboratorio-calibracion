import { Form, Head, Link } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import RoleFormFields from '@/components/roles/role-form-fields';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/roles';
import type { PermissionCatalogGroup, RoleDetail } from '@/types';

type Props = {
    role: RoleDetail;
    catalog: PermissionCatalogGroup[];
};

export default function RolesEdit({ role, catalog }: Props) {
    return (
        <>
            <Head title={`Editar rol ${role.name}`} />

            <h1 className="sr-only">Editar rol</h1>

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={`Editar rol ${role.name}`}
                    description="Actualiza el nombre y los permisos del rol"
                />

                <Form
                    {...RoleController.update.form(role.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <RoleFormFields
                                role={role}
                                catalog={catalog}
                                errors={errors}
                            />

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="role-update-button"
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
        </>
    );
}

RolesEdit.layout = (props: { role: RoleDetail }) => ({
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: `Editar ${props.role.name}`, href: edit(props.role.id) },
    ],
});
