import { RotateCcw, RotateCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import Cropper from 'react-easy-crop';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cropImage, normalizeRotation } from '@/lib/crop-image';
import type { PixelCrop } from '@/lib/crop-image';

const ASPECTS = [
    { label: '1:1', value: 1 },
    { label: '4:3', value: 4 / 3 },
    { label: '16:9', value: 16 / 9 },
];

type Props = {
    file: File | null;
    maxSide?: number;
    onConfirm: (file: File) => void;
    onCancel: () => void;
};

export default function ImageCropDialog({
    file,
    maxSide = 512,
    onConfirm,
    onCancel,
}: Props) {
    const [source, setSource] = useState<string | null>(null);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [aspect, setAspect] = useState(1);
    const [pixels, setPixels] = useState<PixelCrop | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            return;
        }

        const url = URL.createObjectURL(file);

        setSource(url);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setRotation(0);
        setAspect(1);
        setPixels(null);
        setError(null);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const confirm = async () => {
        if (!file || !source || !pixels) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const blob = await cropImage(source, pixels, rotation, maxSide);
            const name = file.name.replace(/\.[^.]+$/, '') || 'imagen';

            onConfirm(new File([blob], `${name}.png`, { type: 'image/png' }));
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'No se pudo editar la imagen.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog
            open={file !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onCancel();
                }
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Ajustar imagen</DialogTitle>
                    <DialogDescription>
                        Arrastra para encuadrar, usa el zoom y gira la imagen.
                        Se guardará como PNG de hasta {maxSide} px.
                    </DialogDescription>
                </DialogHeader>

                <div
                    className="relative h-72 overflow-hidden rounded-md bg-muted"
                    data-test="image-crop-area"
                >
                    {source ? (
                        <Cropper
                            image={source}
                            crop={crop}
                            zoom={zoom}
                            rotation={rotation}
                            aspect={aspect}
                            minZoom={1}
                            maxZoom={4}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onRotationChange={(value) =>
                                setRotation(normalizeRotation(value))
                            }
                            onCropComplete={(_, croppedAreaPixels) =>
                                setPixels(croppedAreaPixels)
                            }
                        />
                    ) : null}
                </div>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="crop-zoom">Zoom</Label>
                        <input
                            id="crop-zoom"
                            type="range"
                            min={1}
                            max={4}
                            step={0.01}
                            value={zoom}
                            onChange={(event) =>
                                setZoom(Number(event.target.value))
                            }
                            className="w-full accent-primary"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="crop-rotation">
                            Rotación ({Math.round(rotation)}°)
                        </Label>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                aria-label="Girar 90° a la izquierda"
                                onClick={() =>
                                    setRotation(
                                        normalizeRotation(rotation - 90),
                                    )
                                }
                            >
                                <RotateCcw className="h-4 w-4" />
                            </Button>
                            <input
                                id="crop-rotation"
                                type="range"
                                min={-180}
                                max={180}
                                step={1}
                                value={rotation}
                                onChange={(event) =>
                                    setRotation(Number(event.target.value))
                                }
                                className="w-full accent-primary"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                aria-label="Girar 90° a la derecha"
                                onClick={() =>
                                    setRotation(
                                        normalizeRotation(rotation + 90),
                                    )
                                }
                            >
                                <RotateCw className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <span className="text-sm font-medium">Proporción</span>
                        <div className="flex gap-2">
                            {ASPECTS.map((option) => (
                                <Button
                                    key={option.label}
                                    type="button"
                                    size="sm"
                                    variant={
                                        aspect === option.value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setAspect(option.value)}
                                >
                                    {option.label}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {error ? (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {error}
                        </p>
                    ) : null}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={confirm}
                        disabled={processing || pixels === null}
                        data-test="image-crop-confirm"
                    >
                        Usar imagen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
