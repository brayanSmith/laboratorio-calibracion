import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProcedimientoCalibracion } from '@/types';

type Props = {
    procedimientoCalibracion?: ProcedimientoCalibracion;
    errors: Partial<Record<string, string>>;
};

export default function ProcedimientoCalibracionFormFields({
    procedimientoCalibracion,
    errors,
}: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={procedimientoCalibracion?.nombre}
                    placeholder="PC-01 Calibración de manómetros"
                    required
                />
                <InputError message={errors.nombre} />
            </div>
        </div>
    );
}
