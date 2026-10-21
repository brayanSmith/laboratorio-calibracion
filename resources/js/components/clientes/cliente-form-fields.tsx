import { usePage } from '@inertiajs/react';
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
import type { Cliente, TipoDocumentoClienteOption } from '@/types';

type Props = {
    cliente?: Cliente;
    errors: Partial<Record<string, string>>;
};

export default function ClienteFormFields({ cliente, errors }: Props) {
    const { tiposDocumento } = usePage<{
        tiposDocumento: TipoDocumentoClienteOption[];
    }>().props;

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="tipo_documento">
                    Tipo de documento{' '}
                    <span className="text-destructive">*</span>
                </Label>
                <Select
                    name="tipo_documento"
                    defaultValue={cliente?.tipo_documento ?? undefined}
                    required
                >
                    <SelectTrigger
                        id="tipo_documento"
                        className="w-full"
                        data-test="cliente-tipo-documento"
                    >
                        <SelectValue placeholder="Selecciona un tipo" />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposDocumento.map((tipo) => (
                            <SelectItem key={tipo.value} value={tipo.value}>
                                {tipo.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_documento} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="numero_documento">
                    Número de documento{' '}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="numero_documento"
                    name="numero_documento"
                    defaultValue={cliente?.numero_documento ?? ''}
                    required
                />
                <InputError message={errors.numero_documento} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">
                    Nombre <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={cliente?.nombre}
                    required
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">
                    Correo electrónico{' '}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={cliente?.email}
                    required
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="telefono">Teléfono</Label>
                <Input
                    id="telefono"
                    name="telefono"
                    defaultValue={cliente?.telefono ?? ''}
                />
                <InputError message={errors.telefono} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    defaultValue={cliente?.direccion ?? ''}
                />
                <InputError message={errors.direccion} />
            </div>
        </div>
    );
}
