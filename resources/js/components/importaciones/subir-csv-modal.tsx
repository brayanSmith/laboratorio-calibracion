import { useHttp } from '@inertiajs/react';
import { Download } from 'lucide-react';
import {
    encabezados,
    plantilla,
} from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionController';
import HistorialImportaciones from '@/components/importaciones/historial-importaciones';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { permisoImportacion, tiposImportacion } from '@/lib/tipos-importacion';
import type { ArchivoImportacion, TipoImportacion } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onLeido: (archivo: ArchivoImportacion) => void;
};

/**
 * Primer paso de la importación: elegir qué se importa (solo los tipos que el usuario tiene
 * permitido importar), el CSV, y leer sus columnas. La pestaña Resultados muestra las
 * últimas importaciones con sus errores, por si el aviso se cerró antes de verlos.
 */
export default function SubirCsvModal({ open, onOpenChange, onLeido }: Props) {
    const { can } = usePermissions();
    const tiposPermitidos = tiposImportacion.filter((tipo) =>
        can(permisoImportacion(tipo.value)),
    );
    const { data, setData, post, processing, progress, errors, reset } =
        useHttp<
            { tipo: TipoImportacion; archivo: File | null },
            ArchivoImportacion
        >({
            tipo: tiposPermitidos[0]?.value ?? 'equipos',
            archivo: null,
        });

    const tipoElegido =
        tiposImportacion.find((tipo) => tipo.value === data.tipo) ??
        tiposImportacion[0];

    const cambiarApertura = (abierta: boolean) => {
        if (!abierta) {
            reset();
        }

        onOpenChange(abierta);
    };

    const continuar = () => {
        void post(encabezados().url, {
            onSuccess: (archivo) => {
                reset('archivo');
                onLeido(archivo);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={cambiarApertura}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Importar datos</DialogTitle>
                    <DialogDescription>
                        Elige qué quieres importar y sube un archivo CSV con un
                        registro por fila y los encabezados en la primera fila.
                        En el siguiente paso relacionarás cada columna con su
                        campo.
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="subir" className="min-w-0">
                    <TabsList className="w-full">
                        <TabsTrigger
                            value="subir"
                            data-test="importar-tab-subir"
                        >
                            Subir archivo
                        </TabsTrigger>
                        <TabsTrigger
                            value="resultados"
                            data-test="importar-tab-resultados"
                        >
                            Resultados
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="subir" className="space-y-4 pt-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tipo-importacion">
                                ¿Qué quieres importar?
                            </Label>
                            <Select
                                value={data.tipo}
                                onValueChange={(valor) =>
                                    setData('tipo', valor as TipoImportacion)
                                }
                            >
                                <SelectTrigger
                                    id="tipo-importacion"
                                    className="w-full"
                                    data-test="importar-tipo"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiposPermitidos.map((tipo) => (
                                        <SelectItem
                                            key={tipo.value}
                                            value={tipo.value}
                                        >
                                            {tipo.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo} />
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-3 py-2">
                            <p className="text-sm text-muted-foreground">
                                ¿No tienes un archivo? Descarga la plantilla de{' '}
                                {tipoElegido.label.toLowerCase()} con todas las
                                columnas y una fila de ejemplo.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                data-test="importar-descargar-plantilla"
                            >
                                <a href={plantilla(data.tipo).url} download>
                                    <Download />
                                    Descargar plantilla
                                </a>
                            </Button>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="archivo-importacion">
                                Archivo CSV
                            </Label>
                            <Input
                                id="archivo-importacion"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(event) =>
                                    setData(
                                        'archivo',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                data-test="importar-archivo-input"
                            />
                            {progress && progress.percentage !== undefined ? (
                                <p className="text-xs text-muted-foreground">
                                    Subiendo archivo... {progress.percentage}%
                                </p>
                            ) : null}
                            <InputError message={errors.archivo} />
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancelar</Button>
                            </DialogClose>
                            <Button
                                type="button"
                                onClick={continuar}
                                disabled={!data.archivo}
                                loading={processing}
                                data-test="importar-continuar"
                            >
                                Continuar
                            </Button>
                        </DialogFooter>
                    </TabsContent>

                    <TabsContent value="resultados" className="space-y-4 pt-2">
                        <HistorialImportaciones />
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="secondary">Cerrar</Button>
                            </DialogClose>
                        </DialogFooter>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
