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
import type { Bahia, BahiaAreaOption } from '@/types';

type Props = {
    bahia?: Bahia;
    areas: BahiaAreaOption[];
    errors: Partial<Record<string, string>>;
};

export default function BahiaFormFields({ bahia, areas, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="area_id">Área</Label>
                <Select name="area_id" defaultValue={bahia?.area_id.toString()}>
                    <SelectTrigger id="area_id" className="w-full">
                        <SelectValue placeholder="Selecciona un área" />
                    </SelectTrigger>
                    <SelectContent>
                        {areas.map((area) => (
                            <SelectItem
                                key={area.id}
                                value={area.id.toString()}
                            >
                                {area.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.area_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={bahia?.nombre}
                    placeholder="Bahía 1"
                    required
                />
                <InputError message={errors.nombre} />
            </div>
        </div>
    );
}
