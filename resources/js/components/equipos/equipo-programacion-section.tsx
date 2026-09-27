import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import EquipoProgramacionController from '@/actions/App/Http/Controllers/EquipoProgramacionController';
import EquipoProgramacionFields, {
    estadosVencimiento,
    tiposServicio,
} from '@/components/equipos/equipo-programacion-fields';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Equipo } from '@/types';

type Props = {
    equipo: Equipo;
};

function tipoServicioLabel(value: string): string {
    return (
        tiposServicio.find((option) => option.value === value)?.label ?? value
    );
}

function estadoVencimientoLabel(value: string): string {
    return (
        estadosVencimiento.find((option) => option.value === value)?.label ??
        value
    );
}

export default function EquipoProgramacionSection({ equipo }: Props) {
    const programaciones = equipo.equipo_programaciones ?? [];

    return (
        <section className="space-y-4" data-test="equipo-programacion">
            <Heading
                variant="small"
                title="Programación de servicio"
                description="Un equipo puede tener varias, por ejemplo una de mantenimiento y otra de calibración"
            />

            <ul className="divide-y rounded-lg border">
                {programaciones.map((programacion) => (
                    <li
                        key={programacion.id}
                        className="flex items-center justify-between gap-2 px-3 py-2"
                        data-test="equipo-programacion-item"
                    >
                        <div className="flex items-center gap-2 text-sm">
                            <span className="font-medium">
                                {tipoServicioLabel(programacion.tipo_servicio)}
                            </span>
                            <Badge variant="secondary">
                                {estadoVencimientoLabel(
                                    programacion.estado_vencimiento,
                                )}
                            </Badge>
                        </div>

                        <Form
                            {...EquipoProgramacionController.destroy.form(
                                programacion.id,
                            )}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    disabled={processing}
                                    aria-label="Eliminar programación"
                                    data-test="equipo-programacion-delete"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            )}
                        </Form>
                    </li>
                ))}

                {programaciones.length === 0 ? (
                    <li className="px-3 py-4 text-center text-sm text-muted-foreground">
                        Este equipo aún no tiene programaciones de servicio.
                    </li>
                ) : null}
            </ul>

            <Form
                {...EquipoProgramacionController.store.form(equipo.id)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-6 rounded-lg border border-dashed p-3"
            >
                {({ errors, processing }) => (
                    <>
                        <EquipoProgramacionFields errors={errors} />

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="equipo-programacion-submit"
                        >
                            Agregar programación
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
