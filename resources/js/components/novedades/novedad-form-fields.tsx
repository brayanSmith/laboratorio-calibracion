import { categoriasNovedad } from '@/components/novedades/categoria-novedad';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Novedad } from '@/types';

type Props = {
    novedad?: Novedad;
    errors: Partial<Record<string, string>>;
};

export default function NovedadFormFields({ novedad, errors }: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="categoria">Categoría</Label>
                <Select name="categoria" defaultValue={novedad?.categoria}>
                    <SelectTrigger id="categoria" className="w-full">
                        <SelectValue placeholder="Selecciona una categoría" />
                    </SelectTrigger>
                    <SelectContent>
                        {categoriasNovedad.map((categoria) => (
                            <SelectItem
                                key={categoria.value}
                                value={categoria.value}
                            >
                                {categoria.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.categoria} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={novedad?.nombre}
                    placeholder="Equipo con daño visible"
                    required
                />
                <InputError message={errors.nombre} />
            </div>
        </div>
    );
}
