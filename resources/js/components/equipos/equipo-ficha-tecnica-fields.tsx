import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Equipo } from '@/types';

type Props = {
    equipo?: Equipo;
    errors: Partial<Record<string, string>>;
};

/**
 * Ficha técnica fields for the equipo form. The caller is responsible for
 * its own heading; move this component around freely to reposition the
 * section.
 */
export default function EquipoFichaTecnicaFields({ equipo, errors }: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="pais_procedencia">País de procedencia</Label>
                <Input
                    id="pais_procedencia"
                    name="pais_procedencia"
                    defaultValue={equipo?.ficha_tecnica?.pais_procedencia}
                    required
                />
                <InputError message={errors.pais_procedencia} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="numero_activo">Número de activo</Label>
                <Input
                    id="numero_activo"
                    name="numero_activo"
                    defaultValue={equipo?.ficha_tecnica?.numero_activo}
                    required
                />
                <InputError message={errors.numero_activo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="proveedor">Proveedor</Label>
                <Input
                    id="proveedor"
                    name="proveedor"
                    defaultValue={equipo?.ficha_tecnica?.proveedor}
                    required
                />
                <InputError message={errors.proveedor} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="costo_usd">Costo (USD)</Label>
                <Input
                    id="costo_usd"
                    name="costo_usd"
                    type="number"
                    step="0.01"
                    min="0"
                    defaultValue={equipo?.ficha_tecnica?.costo_usd}
                    required
                />
                <InputError message={errors.costo_usd} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="fecha_adquisicion">Fecha de adquisición</Label>
                <Input
                    id="fecha_adquisicion"
                    name="fecha_adquisicion"
                    type="date"
                    defaultValue={equipo?.ficha_tecnica?.fecha_adquisicion?.slice(
                        0,
                        10,
                    )}
                    required
                />
                <InputError message={errors.fecha_adquisicion} />
            </div>
        </div>
    );
}
