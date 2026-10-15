import { Camera } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import ImageCropDialog from '@/components/image-crop-dialog';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';

type Props = {
    name: string;
    currentUrl?: string | null;
    error?: string;
};

export default function AvatarField({ name, currentUrl, error }: Props) {
    const getInitials = useInitials();
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

    const shownUrl = preview ?? currentUrl ?? null;

    return (
        <div className="grid gap-2" data-test="foto-section">
            <Label htmlFor="foto">Foto de perfil</Label>

            <div className="flex items-center gap-4">
                <Avatar className="size-20">
                    {shownUrl ? (
                        <AvatarImage
                            src={shownUrl}
                            alt="Tu foto de perfil"
                            data-test="foto-preview"
                        />
                    ) : null}
                    <AvatarFallback className="text-xl">
                        {getInitials(name)}
                    </AvatarFallback>
                </Avatar>

                <div className="space-y-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                    >
                        <Camera />
                        {shownUrl ? 'Cambiar foto' : 'Subir foto'}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        PNG, JPG o WebP de máximo 2 MB. Podrás recortarla antes
                        de guardar.
                    </p>
                </div>
            </div>

            <input
                ref={inputRef}
                id="foto"
                name="foto"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                className="sr-only"
                onChange={handleChange}
            />
            <InputError message={error} />

            {currentUrl ? (
                <div className="flex items-center gap-3">
                    <Checkbox id="eliminar_foto" name="eliminar_foto" />
                    <Label htmlFor="eliminar_foto">Quitar foto actual</Label>
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
