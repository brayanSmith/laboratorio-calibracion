import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/equipos';
import type { Equipo } from '@/types';

type Props = {
    equipo: Equipo;
};

function Field({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-1">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">{value ?? '—'}</span>
        </div>
    );
}

export default function EquiposShow({ equipo }: Props) {
    return (
        <>
            <Head title={`Equipo ${equipo.codigo}`} />

            <h1 className="sr-only">Equipo {equipo.codigo}</h1>

            <div className="max-w-3xl space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={`Equipo ${equipo.codigo}`}
                        description={equipo.modelo}
                    />

                    <Button asChild data-test="equipo-edit-button">
                        <Link href={edit(equipo.id)}>
                            <Pencil /> Editar
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 rounded-lg border p-6 sm:grid-cols-2">
                    <Field label="Código" value={equipo.codigo} />
                    <Field label="Modelo" value={equipo.modelo} />
                    <Field
                        label="Número de serie"
                        value={equipo.numero_serie}
                    />
                    <Field
                        label="Condición actual"
                        value={equipo.condicion_actual}
                    />
                    <Field
                        label="Tipo de tecnología"
                        value={equipo.tipo_tecnologia}
                    />
                    <Field
                        label="Tipo de equipo"
                        value={equipo.tipo_equipo?.nombre}
                    />
                    <Field
                        label="Fabricante"
                        value={equipo.fabricante?.nombre}
                    />
                    <Field label="Área" value={equipo.area?.nombre} />
                    <Field label="Bahía" value={equipo.bahia?.nombre} />
                    <Field label="Cliente" value={equipo.cliente?.nombre} />
                    <Field
                        label="Estado"
                        value={
                            <Badge
                                variant={
                                    equipo.activo ? 'default' : 'secondary'
                                }
                            >
                                {equipo.activo ? 'Activo' : 'Inactivo'}
                            </Badge>
                        }
                    />
                    <Field
                        label="Patrón de referencia"
                        value={equipo.patron_referencia ? 'Sí' : 'No'}
                    />
                    <Field
                        label="Requiere programación"
                        value={equipo.requiere_programacion ? 'Sí' : 'No'}
                    />
                    <div className="sm:col-span-2">
                        <Field label="Notas" value={equipo.notas} />
                    </div>
                </div>
            </div>
        </>
    );
}

EquiposShow.layout = (props: { equipo: Equipo }) => ({
    breadcrumbs: [
        { title: 'Equipos', href: index() },
        { title: props.equipo.codigo, href: show(props.equipo.id) },
    ],
});
