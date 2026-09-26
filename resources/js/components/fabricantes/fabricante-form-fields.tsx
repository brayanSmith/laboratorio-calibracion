import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Fabricante } from '@/types';

type Props = {
    fabricante?: Fabricante;
    errors: Partial<Record<string, string>>;
};

export default function FabricanteFormFields({ fabricante, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={fabricante?.nombre}
                    placeholder="Fluke"
                    required
                />
                <InputError message={errors.nombre} />
            </div>
        </div>
    );
}
