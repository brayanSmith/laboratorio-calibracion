import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/galeria';
import type { MantenimientoFoto } from '@/types';

type Props = {
    galeria: MantenimientoFoto[];
    errors: Partial<Record<string, string>>;
};

/** Relación 1:* (un mantenimiento puede tener varias fotos). Las filas nuevas quedan
 * disponibles para subirse al presionar el botón "Guardar" de la modal. */
export default function MantenimientoGaleria({ galeria, errors }: Props) {
    const siguienteKey = useRef(0);
    const [filas, setFilas] = useState<number[]>(() => [
        siguienteKey.current++,
    ]);
    const [fotoAmpliada, setFotoAmpliada] = useState<MantenimientoFoto | null>(
        null,
    );

    return (
        <div className="space-y-2">
            <Label>Galería de fotos</Label>

            {galeria.length > 0 ? (
                <div
                    className="grid grid-cols-3 gap-2 sm:grid-cols-4"
                    data-test="mantenimiento-galeria-list"
                >
                    {galeria.map((foto) => (
                        <div key={foto.id} className="group relative">
                            <button
                                type="button"
                                onClick={() => setFotoAmpliada(foto)}
                                aria-label="Ver foto en grande"
                                className="block w-full"
                            >
                                <img
                                    src={foto.imagen_url}
                                    alt={
                                        foto.descripcion ??
                                        'Foto del mantenimiento'
                                    }
                                    className="aspect-square w-full rounded-md border object-cover"
                                />
                            </button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="icon"
                                className="absolute top-1 right-1 h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                aria-label="Eliminar foto"
                                onClick={() =>
                                    router.delete(destroy.url(foto.id), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    ))}
                </div>
            ) : null}

            <Dialog
                open={fotoAmpliada !== null}
                onOpenChange={(open) => !open && setFotoAmpliada(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogTitle>
                        {fotoAmpliada?.descripcion || 'Foto del mantenimiento'}
                    </DialogTitle>
                    {fotoAmpliada ? (
                        <img
                            src={fotoAmpliada.imagen_url}
                            alt={
                                fotoAmpliada.descripcion ??
                                'Foto del mantenimiento'
                            }
                            className="max-h-[75vh] w-full rounded-md object-contain"
                        />
                    ) : null}
                </DialogContent>
            </Dialog>

            <div className="space-y-2">
                {filas.map((key, index) => (
                    <div
                        key={key}
                        className="flex items-start gap-2 rounded-md border bg-muted/30 p-2"
                        data-test="foto-fila-nueva"
                    >
                        <div className="flex-1 space-y-2">
                            <Input
                                name={`nuevas_fotos[${index}][archivo]`}
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                            />
                            <InputError
                                message={
                                    errors[`nuevas_fotos.${index}.archivo`]
                                }
                            />
                            <Input
                                name={`nuevas_fotos[${index}][descripcion]`}
                                placeholder="Descripción (opcional)"
                            />
                            <InputError
                                message={
                                    errors[`nuevas_fotos.${index}.descripcion`]
                                }
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 shrink-0"
                            aria-label="Quitar fila"
                            disabled={filas.length === 1}
                            onClick={() =>
                                setFilas((prev) =>
                                    prev.filter((otra) => otra !== key),
                                )
                            }
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                ))}

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        setFilas((prev) => [...prev, siguienteKey.current++])
                    }
                    data-test="agregar-fila-foto-button"
                >
                    <Plus className="h-4 w-4" /> Agregar otra foto
                </Button>
            </div>
        </div>
    );
}
