import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import ImageCropDialog from '@/components/image-crop-dialog';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Empresa } from '@/types';

type Props = {
    empresa?: Empresa;
    error?: string;
};

export default function LogoField({ empresa, error }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [pending, setPending] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (file && file.type.startsWith('image/')) {
            setPending(file);
        }
    };

    const handleConfirm = (edited: File) => {
        const input = inputRef.current;

        if (input) {
            const transfer = new DataTransfer();

            transfer.items.add(edited);
            input.files = transfer.files;
        }

        setPreview(URL.createObjectURL(edited));
        setPending(null);
    };

    const handleCancel = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setPreview(null);
        setPending(null);
    };

    const currentLogo = preview ?? empresa?.logo_url ?? null;

    return (
        <div className="grid gap-2 sm:col-span-2">
            <Label htmlFor="logo">Logo</Label>

            {currentLogo ? (
                <img
                    src={currentLogo}
                    alt={
                        preview
                            ? 'Vista previa del logo nuevo'
                            : `Logo de ${empresa?.nombre}`
                    }
                    className="h-16 w-fit rounded-md border bg-white object-contain p-1"
                    data-test="logo-preview"
                />
            ) : null}

            <Input
                ref={inputRef}
                id="logo"
                name="logo"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={handleChange}
            />
            <p className="text-xs text-muted-foreground">
                Al elegir una imagen podrás recortarla, girarla y ajustar su
                tamaño.
                {empresa?.logo_url
                    ? ' Si eliges una nueva, reemplaza a la actual.'
                    : ''}
            </p>
            <InputError message={error} />

            {empresa?.logo_url ? (
                <div className="flex items-center gap-3">
                    <Checkbox id="eliminar_logo" name="eliminar_logo" />
                    <Label htmlFor="eliminar_logo">Quitar logo actual</Label>
                </div>
            ) : null}

            <ImageCropDialog
                file={pending}
                onConfirm={handleConfirm}
                onCancel={handleCancel}
            />
        </div>
    );
}
