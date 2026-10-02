import { useState } from 'react';
import Combobox from '@/components/combobox';
import EquipoProgramacionesBuscador from '@/components/ingresos/equipo-programaciones-buscador';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Ingreso, IngresoOption, IngresoOptions } from '@/types';

type Props = {
    ingreso?: Ingreso;
    options: IngresoOptions;
    errors: Partial<Record<string, string>>;
    /** Vista previa de los equipos que se esperan recibir, para crear o revisar el ingreso. */
    mostrarBuscador?: boolean;
};

function toComboboxOptions(options: IngresoOption[]) {
    return options.map((option) => ({ id: option.id, label: option.nombre }));
}

/**
 * Bahía, fechas y equipos del ingreso. El técnico que recibe, el cliente que entrega,
 * la firma, la novedad y el motivo de cancelación se manejan aparte, desde "Recibir"
 * (ver recibir-ingreso-modal.tsx), no desde este formulario.
 */
export default function IngresoFormFields({
    ingreso,
    options,
    errors,
    mostrarBuscador = false,
}: Props) {
    const [desde, setDesde] = useState(ingreso?.desde ?? '');
    const [hasta, setHasta] = useState(ingreso?.hasta ?? '');
    const [bahiaId, setBahiaId] = useState(ingreso?.bahia_id.toString());

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="desde">Desde</Label>
                <Input
                    id="desde"
                    name="desde"
                    type="date"
                    value={desde}
                    onChange={(event) => {
                        const nuevoDesde = event.target.value;

                        setDesde(nuevoDesde);

                        // Si "hasta" queda antes de la nueva fecha "desde", se ajusta para no
                        // dejar un rango inválido, aunque el usuario ya la hubiera escrito.
                        if (hasta !== '' && hasta < nuevoDesde) {
                            setHasta(nuevoDesde);
                        }
                    }}
                    required
                />
                <InputError message={errors.desde} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="hasta">Hasta</Label>
                <Input
                    id="hasta"
                    name="hasta"
                    type="date"
                    value={hasta}
                    onChange={(event) => setHasta(event.target.value)}
                    min={desde || undefined}
                    required
                />
                <InputError message={errors.hasta} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="bahia_id">Bahía</Label>
                <Combobox
                    id="bahia_id"
                    name="bahia_id"
                    value={bahiaId}
                    onValueChange={setBahiaId}
                    options={toComboboxOptions(options.bahias)}
                    placeholder="Selecciona una bahía"
                    searchPlaceholder="Buscar bahía..."
                />
                <InputError message={errors.bahia_id} />
            </div>

            {mostrarBuscador ? (
                <div className="grid gap-2 sm:col-span-2">
                    <EquipoProgramacionesBuscador
                        desde={desde}
                        hasta={hasta}
                        bahiaId={bahiaId}
                        resultadosIniciales={ingreso?.equipo_programaciones}
                        errors={errors}
                    />
                </div>
            ) : null}
        </div>
    );
}
