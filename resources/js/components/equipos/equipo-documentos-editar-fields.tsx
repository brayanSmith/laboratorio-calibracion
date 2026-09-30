import { FileText } from 'lucide-react';
import EquipoDocumentosFields from '@/components/equipos/equipo-documentos-fields';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { DocumentoEquipoItem } from '@/types';

type Props = {
    documentos: DocumentoEquipoItem[];
    errors: Partial<Record<string, string>>;
};

/**
 * Documentos existentes (con casilla para marcarlos a eliminar) más el
 * repetidor para agregar nuevos. Todo viaja en el mismo envío del formulario,
 * sin botones de guardar por separado.
 */
export default function EquipoDocumentosEditarFields({
    documentos,
    errors,
}: Props) {
    return (
        <div className="space-y-3">
            {documentos.length > 0 ? (
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

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`documento-eliminar-${documento.id}`}
                                    name="documentos_eliminar[]"
                                    value={documento.id.toString()}
                                />
                                <Label
                                    htmlFor={`documento-eliminar-${documento.id}`}
                                    className="text-sm font-normal text-muted-foreground"
                                >
                                    Eliminar
                                </Label>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-center text-sm text-muted-foreground">
                    Este equipo aún no tiene documentos.
                </p>
            )}

            <EquipoDocumentosFields errors={errors} />
        </div>
    );
}
