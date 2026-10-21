import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ArchivoImportacion,
    ResumenImportacion,
    TipoImportacion,
} from '@/types';

/** Valor del select para un campo que no se toma de ninguna columna. */
const SIN_COLUMNA = 'ninguna';

type Props = {
    archivo: ArchivoImportacion;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAtras: () => void;
    onIniciada: (importacion: ResumenImportacion) => void;
};

type DatosImportacion = {
    tipo: TipoImportacion;
    archivo: string;
    nombre_original: string;
    mapeo: Record<string, number | null>;
};

/**
 * Segundo paso: relacionar cada campo del tipo elegido con una columna del CSV, porque los
 * encabezados pueden cambiar de nombre u orden entre archivos. Arranca con las columnas
 * cuyos encabezados se parecen al campo.
 */
export default function RelacionarColumnasModal({
    archivo,
    open,
    onOpenChange,
    onAtras,
    onIniciada,
}: Props) {
    const { data, setData, post, processing, errors } = useHttp<
        DatosImportacion,
        ResumenImportacion
    >({
        tipo: archivo.tipo,
        archivo: archivo.archivo,
        nombre_original: archivo.nombre_original,
        mapeo: archivo.sugerencias,
    });

    const errores = errors as Partial<Record<string, string>>;
    const primeraFila = archivo.vista_previa[0] ?? [];

    const importar = () => {
        post(store().url, {
            onSuccess: onIniciada,
            onHttpException: () => {
                toast.error('No se pudo iniciar la importación.');
            },
        }).catch(() => undefined);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Relacionar columnas · {archivo.tipo_etiqueta}
                    </DialogTitle>
                    <DialogDescription>
                        Elige qué columna de{' '}
                        <strong>{archivo.nombre_original}</strong> corresponde a
                        cada campo. Los marcados con * son obligatorios.
                        {archivo.nota ? ` ${archivo.nota}` : null}
                    </DialogDescription>
                </DialogHeader>

                <InputError message={errores.archivo} />

                <div
                    className="divide-y rounded-lg border"
                    data-test="importar-mapeo"
                >
                    <div className="hidden grid-cols-[1fr_1fr_1fr] gap-3 bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground sm:grid">
                        <span>Campo</span>
                        <span>Columna del CSV</span>
                        <span>Ejemplo (primera fila)</span>
                    </div>
                    {archivo.campos.map((campo) => {
                        const indice = data.mapeo[campo.clave] ?? null;
                        const error = errores[`mapeo.${campo.clave}`];

                        return (
                            <div
                                key={campo.clave}
                                className="grid items-center gap-2 px-3 py-2 sm:grid-cols-[1fr_1fr_1fr] sm:gap-3"
                            >
                                <div className="text-sm">
                                    <label
                                        htmlFor={`mapeo-${campo.clave}`}
                                        className="font-medium"
                                    >
                                        {campo.etiqueta}
                                        {campo.requerido ? (
                                            <span className="text-destructive">
                                                {' '}
                                                *
                                            </span>
                                        ) : null}
                                    </label>
                                    {campo.ayuda ? (
                                        <p className="text-xs text-muted-foreground">
                                            {campo.ayuda}
                                        </p>
                                    ) : null}
                                </div>
                                <div>
                                    <Select
                                        value={
                                            indice === null
                                                ? SIN_COLUMNA
                                                : String(indice)
                                        }
                                        onValueChange={(valor) =>
                                            setData('mapeo', {
                                                ...data.mapeo,
                                                [campo.clave]:
                                                    valor === SIN_COLUMNA
                                                        ? null
                                                        : Number(valor),
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`mapeo-${campo.clave}`}
                                            className="w-full"
                                            aria-invalid={!!error}
                                            data-test={`mapeo-${campo.clave}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={SIN_COLUMNA}>
                                                — No importar —
                                            </SelectItem>
                                            {archivo.encabezados.map(
                                                (encabezado, posicion) => (
                                                    <SelectItem
                                                        key={posicion}
                                                        value={String(posicion)}
                                                    >
                                                        {encabezado}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={error} />
                                </div>
                                <span className="truncate text-sm text-muted-foreground">
                                    {indice === null
                                        ? '—'
                                        : primeraFila[indice] || '(vacío)'}
                                </span>
                            </div>
                        );
                    })}
                </div>

                <DialogFooter className="gap-2">
                    <Button type="button" variant="secondary" onClick={onAtras}>
                        Atrás
                    </Button>
                    <Button
                        type="button"
                        onClick={importar}
                        loading={processing}
                        data-test="importar-iniciar"
                    >
                        Importar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
