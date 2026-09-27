import { Form } from '@inertiajs/react';
import { FileText, Trash2 } from 'lucide-react';
import DocumentoEquipoController from '@/actions/App/Http/Controllers/DocumentoEquipoController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Equipo } from '@/types';

type Props = {
    equipo: Equipo;
};

export default function EquipoDocumentosSection({ equipo }: Props) {
    const documentos = equipo.equipo_documentos ?? [];

    return (
        <section className="space-y-4" data-test="equipo-documentos">
            <Heading
                variant="small"
                title="Documentos"
                description="Manuales, fichas técnicas y otros archivos de este equipo"
            />

            <ul className="divide-y rounded-lg border">
                {documentos.map((documento) => (
                    <li
                        key={documento.id}
                        className="flex items-center justify-between gap-2 px-3 py-2"
                        data-test="documento-item"
                    >
                        <a
                            href={documento.archivo_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2 text-sm hover:underline"
                        >
                            <FileText className="h-4 w-4 shrink-0" />
                            {documento.nombre}
                        </a>

                        <Form
                            {...DocumentoEquipoController.destroy.form(
                                documento.id,
                            )}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    disabled={processing}
                                    aria-label={`Eliminar ${documento.nombre}`}
                                    data-test="documento-delete"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            )}
                        </Form>
                    </li>
                ))}

                {documentos.length === 0 ? (
                    <li className="px-3 py-4 text-center text-sm text-muted-foreground">
                        Este equipo aún no tiene documentos.
                    </li>
                ) : null}
            </ul>

            <Form
                {...DocumentoEquipoController.store.form(equipo.id)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-4 rounded-lg border border-dashed p-3 sm:grid-cols-2"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="documento_nombre">Nombre</Label>
                            <Input
                                id="documento_nombre"
                                name="nombre"
                                placeholder="Manual de usuario"
                                required
                            />
                            <InputError message={errors.nombre} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="documento_archivo">Archivo</Label>
                            <Input
                                id="documento_archivo"
                                name="archivo"
                                type="file"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp"
                                required
                            />
                            <InputError message={errors.archivo} />
                        </div>

                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="equipo-documento-submit"
                            >
                                Agregar documento
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
