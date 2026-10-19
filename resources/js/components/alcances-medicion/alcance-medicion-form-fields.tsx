import { useEffect, useState } from "react";
import Combobox from "@/components/combobox";
import InputError from "@/components/input-error";
import { Label } from "@/components/ui/label";
import type {
    AlcanceMedicion,
    AlcanceMedicionOpcion,
    AlcancesIndicacionPorModelo,
    EspecificacionAlcance,
    EspecificacionesAlcance,
} from "@/types";

type Props = {
    alcanceMedicion?: AlcanceMedicion;
    tiposEquipo: AlcanceMedicionOpcion[];
    alcancesIndicacion: AlcancesIndicacionPorModelo;
    especificaciones: EspecificacionesAlcance;
    onEspecificacionChange: (especificacion: EspecificacionAlcance | null) => void;
    errors: Partial<Record<string, string>>;
};

export default function AlcanceMedicionFormFields({
    alcanceMedicion,
    tiposEquipo,
    alcancesIndicacion,
    especificaciones,
    onEspecificacionChange,
    errors,
}: Props) {
    const [tipoEquipoId, setTipoEquipoId] = useState(
        alcanceMedicion ? String(alcanceMedicion.tipo_equipo_id) : "",
    );
    const [modelo, setModelo] = useState(alcanceMedicion?.modelo ?? "");
    const [alcanceIndicacion, setAlcanceIndicacion] = useState(
        alcanceMedicion?.alcance_indicacion ?? "",
    );

    const especificacion =
        especificaciones[`${tipoEquipoId}|${modelo}|${alcanceIndicacion}`] ??
        null;

    useEffect(() => {
        onEspecificacionChange(especificacion);
    }, [especificacion, onEspecificacionChange]);

    const esGuardado =
        alcanceMedicion !== undefined &&
        tipoEquipoId === String(alcanceMedicion.tipo_equipo_id);
    const delTipo = alcancesIndicacion[tipoEquipoId] ?? {};
    // Al editar se conservan los valores guardados aunque ya no estén en los equipos.
    const modelos =
        esGuardado && !(alcanceMedicion.modelo in delTipo)
            ? [alcanceMedicion.modelo, ...Object.keys(delTipo)]
            : Object.keys(delTipo);
    const delModelo = delTipo[modelo] ?? [];
    const opciones =
        esGuardado &&
        modelo === alcanceMedicion.modelo &&
        !delModelo.includes(alcanceMedicion.alcance_indicacion)
            ? [alcanceMedicion.alcance_indicacion, ...delModelo]
            : delModelo;

    return (
        <div className="grid gap-6 sm:grid-cols-3">
            <div className="grid gap-2">
                <Label htmlFor="tipo_equipo_id">Tipo de equipo</Label>
                <Combobox
                    id="tipo_equipo_id"
                    name="tipo_equipo_id"
                    value={tipoEquipoId}
                    onValueChange={(value) => {
                        setTipoEquipoId(value);
                        setModelo("");
                        setAlcanceIndicacion("");
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
                <Label htmlFor="modelo">Modelo</Label>
                <Combobox
                    key={tipoEquipoId}
                    id="modelo"
                    name="modelo"
                    value={modelo}
                    onValueChange={(value) => {
                        setModelo(value);
                        setAlcanceIndicacion("");
                    }}
                    options={modelos.map((nombre) => ({
                        id: nombre,
                        label: nombre,
                    }))}
                    disabled={tipoEquipoId === ""}
                    placeholder={
                        tipoEquipoId === ""
                            ? "Primero selecciona un tipo de equipo"
                            : modelos.length === 0
                              ? "Este tipo de equipo no tiene modelos registrados"
                              : "Selecciona un modelo"
                    }
                    searchPlaceholder="Buscar modelo..."
                />
                <InputError message={errors.modelo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alcance_indicacion">
                    Alcance de indicación
                </Label>
                <Combobox
                    key={`${tipoEquipoId}|${modelo}`}
                    id="alcance_indicacion"
                    name="alcance_indicacion"
                    value={alcanceIndicacion}
                    onValueChange={setAlcanceIndicacion}
                    options={opciones.map((alcance) => ({
                        id: alcance,
                        label: alcance,
                    }))}
                    disabled={modelo === ""}
                    placeholder={
                        modelo === ""
                            ? "Primero selecciona un modelo"
                            : opciones.length === 0
                              ? "Este modelo no tiene alcances registrados"
                              : "Selecciona un alcance de indicación"
                    }
                    searchPlaceholder="Buscar alcance..."
                />
                <InputError message={errors.alcance_indicacion} />
            </div>
        </div>
    );
}
