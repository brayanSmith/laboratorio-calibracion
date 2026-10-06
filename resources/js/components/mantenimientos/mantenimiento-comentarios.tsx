import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy } from '@/routes/comentarios';
import type { MantenimientoComentario } from '@/types';

type Props = {
    comentarios: MantenimientoComentario[];
    errors: Partial<Record<string, string>>;
};

type FilaBorrador = {
    key: number;
    comentario: string;
};

/** Relación 1:* (un mantenimiento puede tener varios comentarios). Las filas nuevas
 * quedan disponibles para registrarse al presionar el botón "Guardar" de la modal. */
export default function MantenimientoComentarios({
    comentarios,
    errors,
}: Props) {
    const siguienteKey = useRef(0);
    const filaVacia = (): FilaBorrador => ({
        key: siguienteKey.current++,
        comentario: '',
    });

    const [filas, setFilas] = useState<FilaBorrador[]>(() => [filaVacia()]);

    return (
        <div className="space-y-2">
            <Label>Comentarios</Label>

            {comentarios.length > 0 ? (
                <ul
                    className="max-h-40 space-y-2 overflow-y-auto"
                    data-test="mantenimiento-comentarios-list"
                >
                    {comentarios.map((comentario) => (
                        <li
                            key={comentario.id}
                            className="rounded-md border bg-background p-2"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="text-sm">
                                        {comentario.comentario}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {comentario.autor} ·{' '}
                                        {new Date(
                                            comentario.fecha,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="h-6 w-6 shrink-0"
                                    aria-label="Eliminar comentario"
                                    onClick={() =>
                                        router.delete(
                                            destroy.url(comentario.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : null}

            <div className="space-y-2">
                {filas.map((fila, index) => (
                    <div key={fila.key} className="flex items-start gap-2">
                        <div className="flex-1">
                            <Textarea
                                name={`nuevos_comentarios[${index}]`}
                                value={fila.comentario}
                                onChange={(event) =>
                                    setFilas((prev) =>
                                        prev.map((otra) =>
                                            otra.key === fila.key
                                                ? {
                                                      ...otra,
                                                      comentario:
                                                          event.target.value,
                                                  }
                                                : otra,
                                        ),
                                    )
                                }
                                placeholder="Escribe un comentario (opcional)"
                                rows={2}
                            />
                            <InputError
                                message={errors[`nuevos_comentarios.${index}`]}
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
                                    prev.filter(
                                        (otra) => otra.key !== fila.key,
                                    ),
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
                    onClick={() => setFilas((prev) => [...prev, filaVacia()])}
                    data-test="agregar-fila-comentario-button"
                >
                    <Plus className="h-4 w-4" /> Agregar otro comentario
                </Button>
            </div>
        </div>
    );
}
