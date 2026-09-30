import { useState } from 'react';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo?: Equipo;
    options: EquipoFormOptions;
    errors: Partial<Record<string, string>>;
};

/**
 * Área y bahía fields for the equipo form. The caller is responsible for its
 * own heading; move this component around freely to reposition the section.
 * La bahía disponible depende del área seleccionada.
 */
export default function EquipoUbicacionFields({
    equipo,
    options,
    errors,
}: Props) {
    const [areaId, setAreaId] = useState(equipo?.area_id?.toString());
    const [bahiaId, setBahiaId] = useState(equipo?.bahia_id?.toString());

    const bahiasDelArea = options.bahias.filter(
        (bahia) => bahia.area_id.toString() === areaId,
    );

    function handleAreaChange(nuevaAreaId: string) {
        setAreaId(nuevaAreaId);
        setBahiaId(undefined);
    }

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="area_id">Área</Label>
                <Combobox
                    id="area_id"
                    name="area_id"
                    value={areaId}
                    onValueChange={handleAreaChange}
                    options={options.areas.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder="Selecciona un área"
                    searchPlaceholder="Buscar área..."
                />
                <InputError message={errors.area_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="bahia_id">Bahía</Label>
                <Combobox
                    id="bahia_id"
                    name="bahia_id"
                    value={bahiaId}
                    onValueChange={setBahiaId}
                    disabled={!areaId}
                    options={bahiasDelArea.map((option) => ({
                        id: option.id,
                        label: option.nombre,
                    }))}
                    placeholder={
                        areaId
                            ? 'Selecciona una bahía'
                            : 'Selecciona primero un área'
                    }
                    searchPlaceholder="Buscar bahía..."
                />
                <InputError message={errors.bahia_id} />
            </div>
        </div>
    );
}
