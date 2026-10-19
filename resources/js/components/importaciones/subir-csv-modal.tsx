import { useHttp } from '@inertiajs/react';
import { Download } from 'lucide-react';
import {
    encabezados,
    plantilla,
} from '@/actions/App/Modules/Importaciones/Http/Controllers/ImportacionEquipoController';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { ArchivoImportacion } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onLeido: (archivo: ArchivoImportacion) => void;
};

/**
 * Primer paso de la importación: elegir el CSV y leer sus columnas. La pestaña Resultados
 * muestra las últimas importaciones con sus errores, por si el aviso se cerró antes de verlos.
 */
export default function SubirCsvModal({ open, onOpenChange, onLeido }: Props) {
    const { data, setData, post, processing, progress, errors, reset } =
        useHttp<{ archivo: File | null }, ArchivoImportacion>({
            archivo: null,
        });

    const cambiarApertura = (abierta: boolean) => {
        if (!abierta) {
            reset();
        }

        onOpenChange(abierta);
    };

    const continuar = () => {
        void post(encabezados().url, {
            onSuccess: (archivo) => {
                reset();
                onLeido(archivo);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={cambiarApertura}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Importar equipos</DialogTitle>
                    <DialogDescription>
                        Sube un archivo CSV con un equipo por fila y los
                        encabezados en la primera fila. En el siguiente paso
                        relacionarás cada columna con su campo.
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
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-3 py-2">
                            <p className="text-sm text-muted-foreground">
                                ¿No tienes un archivo? Descarga la plantilla con
                                todas las columnas y una fila de ejemplo.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                data-test="importar-descargar-plantilla"
                            >
                                <a href={plantilla().url} download>
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
