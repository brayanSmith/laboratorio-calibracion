import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { UnidadMedida } from '@/types';

type Props = {
    unidadMedida?: UnidadMedida;
    errors: Partial<Record<string, string>>;
};

export default function UnidadMedidaFormFields({
    unidadMedida,
    errors,
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={unidadMedida?.nombre}
                    placeholder="Bar"
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="simbolo">Símbolo</Label>
                <Input
                    id="simbolo"
                    name="simbolo"
                    defaultValue={unidadMedida?.simbolo}
                    placeholder="bar"
                    required
                />
                <InputError message={errors.simbolo} />
            </div>
        </div>
    );
}
