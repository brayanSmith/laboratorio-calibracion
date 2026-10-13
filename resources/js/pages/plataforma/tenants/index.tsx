import { Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    FilaFiltrosServidor,
    LimpiarFiltrosServidor,
} from '@/components/filas-filtro-servidor';
import type { ColumnaFiltro } from '@/components/filas-filtro-servidor';
import Heading from '@/components/heading';
import ThRedimensionable from '@/components/th-redimensionable';
import { ColgroupAnchos } from '@/components/column-resize-handle';
import { useResizableTable } from '@/hooks/use-resizable-table';
import { cn } from '@/lib/utils';
import CreateTenantModal from '@/components/tenants/create-tenant-modal';
import DeleteTenantModal from '@/components/tenants/delete-tenant-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/plataforma/tenants';
import type { Tenant, TenantPaginator } from '@/types';

type Props = {
    tenants: TenantPaginator;
    /** Filtros por columna que el servidor aplicó a la lista. */
    filtros: Record<string, string>;
};

const CLAVES_COLUMNAS = ['nombre', 'slug', 'usuarios', 'activo', 'acciones'];

const COLUMNAS_FILTRO: ColumnaFiltro[] = [
    { clave: 'nombre', etiqueta: 'Nombre' },
    { clave: 'slug', etiqueta: 'Slug' },
    { clave: 'usuarios', etiqueta: 'Usuarios' },
    {
        clave: 'activo',
        etiqueta: 'Estado',
        opciones: [
            { value: '1', label: 'Activo' },
            { value: '0', label: 'Inactivo' },
        ],
    },
    null,
];

export default function TenantsIndex({ tenants, filtros }: Props) {
    const redimension = useResizableTable(CLAVES_COLUMNAS);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [tenantDeleting, setTenantDeleting] = useState<Tenant | null>(null);

    const openDeleteDialog = (tenant: Tenant) => {
        setTenantDeleting(tenant);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Tenants" />

            <h1 className="sr-only">Tenants</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Tenants"
                        description="Gestiona los laboratorios registrados en la plataforma"
                    />

                    <CreateTenantModal>
                        <Button data-test="tenants-new-button">
                            <Plus /> Nuevo tenant
                        </Button>
                    </CreateTenantModal>
                </div>

                <LimpiarFiltrosServidor valores={filtros} url={index().url} />

                <div className="overflow-x-auto rounded-lg border">
                    <table
                        ref={redimension.tablaRef}
                        style={redimension.estiloTabla}
                        className={cn(
                            'w-full text-sm',
                            redimension.anchos &&
                                '[&_td]:break-words [&_th]:break-words',
                        )}
                    >
                        <ColgroupAnchos
                            claves={CLAVES_COLUMNAS}
                            anchos={redimension.anchos}
                        />
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <ThRedimensionable
                                    clave="nombre"
                                    redimension={redimension}
                                >
                                    Nombre
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="slug"
                                    redimension={redimension}
                                >
                                    Slug
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="usuarios"
                                    redimension={redimension}
                                >
                                    Usuarios
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="activo"
                                    redimension={redimension}
                                >
                                    Estado
                                </ThRedimensionable>
                                <th className="px-4 py-3" />
                            </tr>
                            <FilaFiltrosServidor
                                columnas={COLUMNAS_FILTRO}
                                valores={filtros}
                                url={index().url}
                            />
                        </thead>
                        <tbody className="divide-y">
                            {tenants.data.map((tenant) => (
                                <tr key={tenant.id} data-test="tenant-row">
                                    <td className="px-4 py-3 font-medium">
                                        {tenant.nombre}
                                    </td>
                                    <td className="px-4 py-3">{tenant.slug}</td>
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
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={show(tenant.id)}
                                                    data-test="tenant-show-button"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={edit(tenant.id)}
                                                    data-test="tenant-edit-button"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                data-test="tenant-delete-button"
                                                onClick={() =>
                                                    openDeleteDialog(tenant)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {tenants.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        {Object.keys(filtros).length > 0
                                            ? 'No se encontraron tenants con esos filtros.'
                                            : 'Aún no hay tenants registrados.'}
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                {tenants.last_page > 1 ? (
                    <div className="flex flex-wrap items-center gap-1">
                        {tenants.links.map((link, linkIndex) =>
                            link.url ? (
                                <Button
                                    key={linkIndex}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ) : (
                                <Button
                                    key={linkIndex}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>

            <DeleteTenantModal
                tenant={tenantDeleting}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

TenantsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tenants',
            href: index(),
        },
    ],
};
