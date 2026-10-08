import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import type { AlcanceMedicion, AlcanceMedicionOpcion } from '@/types';

type Props = {
    alcanceMedicion?: AlcanceMedicion;
    tiposEquipo: AlcanceMedicionOpcion[];
    alcancesIndicacion: Record<string, string[]>;
    errors: Partial<Record<string, string>>;
};

export default function AlcanceMedicionFormFields({
    alcanceMedicion,
    tiposEquipo,
    alcancesIndicacion,
    errors,
}: Props) {
    const [tipoEquipoId, setTipoEquipoId] = useState(
        alcanceMedicion ? String(alcanceMedicion.tipo_equipo_id) : '',
    );
    const [alcanceIndicacion, setAlcanceIndicacion] = useState(
        alcanceMedicion?.alcance_indicacion ?? '',
    );

    const delTipo = alcancesIndicacion[tipoEquipoId] ?? [];
    // Al editar se conserva el valor guardado aunque ya no esté en las especificaciones.
    const opciones =
        alcanceMedicion &&
        tipoEquipoId === String(alcanceMedicion.tipo_equipo_id) &&
        !delTipo.includes(alcanceMedicion.alcance_indicacion)
            ? [alcanceMedicion.alcance_indicacion, ...delTipo]
            : delTipo;

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="tipo_equipo_id">Tipo de equipo</Label>
                <Combobox
                    id="tipo_equipo_id"
                    name="tipo_equipo_id"
                    value={tipoEquipoId}
                    onValueChange={(value) => {
                        setTipoEquipoId(value);
                        setAlcanceIndicacion('');
                    }}
                    options={tiposEquipo.map((tipoEquipo) => ({
                        id: tipoEquipo.id,
                        label: tipoEquipo.nombre,
                    }))}
                    placeholder="Selecciona un tipo de equipo"
                    searchPlaceholder="Buscar tipo de equipo..."
                />
                <InputError message={errors.tipo_equipo_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alcance_indicacion">
                    Alcance de indicación
                </Label>
                <Combobox
                    key={tipoEquipoId}
                    id="alcance_indicacion"
                    name="alcance_indicacion"
                    value={alcanceIndicacion}
                    onValueChange={setAlcanceIndicacion}
                    options={opciones.map((alcance) => ({
                        id: alcance,
                        label: alcance,
                    }))}
                    disabled={tipoEquipoId === ''}
                    placeholder={
                        tipoEquipoId === ''
                            ? 'Primero selecciona un tipo de equipo'
                            : opciones.length === 0
                              ? 'Este tipo de equipo no tiene alcances registrados'
                              : 'Selecciona un alcance de indicación'
                    }
                    searchPlaceholder="Buscar alcance..."
                />
                <InputError message={errors.alcance_indicacion} />
            </div>
        </div>
    );
}
