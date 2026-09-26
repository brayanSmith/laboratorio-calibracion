import InputError from "@/components/input-error";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Item } from "@/types";

type Props = {
    item?: Item;
    errors: Partial<Record<string, string>>;
};

export default function ItemFormFields({ item, errors }: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="codigo">Código</Label>
                <Input
                    id="codigo"
                    name="codigo"
                    defaultValue={item?.codigo}
                    placeholder="ITM-001"
                    required
                />
                <InputError message={errors.codigo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={item?.nombre}
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Input
                    id="descripcion"
                    name="descripcion"
                    defaultValue={item?.descripcion ?? ""}
                />
                <InputError message={errors.descripcion} />
            </div>
        </div>
    );
}
