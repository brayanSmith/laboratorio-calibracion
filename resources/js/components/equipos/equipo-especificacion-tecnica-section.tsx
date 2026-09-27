import { Form } from '@inertiajs/react';
import EquipoEspecificacionTecnicaController from '@/actions/App/Http/Controllers/EquipoEspecificacionTecnicaController';
import EquipoEspecificacionTecnicaFields from '@/components/equipos/equipo-especificacion-tecnica-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo: Equipo;
    options: EquipoFormOptions;
};

export default function EquipoEspecificacionTecnicaSection({
    equipo,
    options,
}: Props) {
    return (
        <section
            className="space-y-4"
            data-test="equipo-especificacion-tecnica"
        >
            <Heading
                variant="small"
                title="Especificación técnica"
                description="Magnitud, unidad y alcance que mide este equipo"
            />

            <Form
                {...EquipoEspecificacionTecnicaController.store.form(equipo.id)}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <EquipoEspecificacionTecnicaFields
                            especificacion={
                                equipo.equipo_especificacion_tecnica
                            }
                            options={options}
                            errors={errors}
                            required
                        />

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="equipo-especificacion-tecnica-submit"
                        >
                            Guardar especificación técnica
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
