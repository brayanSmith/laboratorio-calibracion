import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ServicioTerceroController from '@/actions/App/Modules/ServiciosTerceros/Http/Controllers/ServicioTerceroController';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import CodigosServicioFields from '@/components/servicio-terceros/codigos-servicio-fields';
import EquipoServicioCard from '@/components/servicio-terceros/equipo-servicio-card';
import VistaPreviaPdf from '@/components/vista-previa-pdf';
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
import type { MantenimientoOption, ServicioTerceroListado } from '@/types';

type Props = {
    servicio: ServicioTerceroListado | null;
    empresasTerceras: MantenimientoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Modal para editar un servicio de tercero: la empresa que lo realiza y el
 * documento (PDF) con la documentación del servicio. También se abre al
 * confirmar "Iniciar", para que se suba la documentación. */
export default function ServicioTerceroModal({
    servicio,
    empresasTerceras,
    open,
    onOpenChange,
}: Props) {
    const [empresaTerceroId, setEmpresaTerceroId] = useState(
        servicio?.empresa_tercero_id.toString() ?? '',
    );
    const [archivoPdf, setArchivoPdf] = useState<File | null>(null);
    const cambiarAbierta = (abierta: boolean) => {
        if (!abierta) {
            setArchivoPdf(null);
        }

        onOpenChange(abierta);
    };

    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={cambiarAbierta}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <Form
                    key={`${servicio.id}-${String(open)}`}
                    {...ServicioTerceroController.update.form(servicio.id)}
                    className="space-y-6"
                    onSuccess={() => cambiarAbierta(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Servicio de tercero</DialogTitle>
                                <DialogDescription>
                                    {servicio.orden_trabajo_codigo} ·{' '}
                                    {servicio.equipo_codigo} ·{' '}
                                    {servicio.equipo_modelo}
                                </DialogDescription>
                            </DialogHeader>

                            {/* Los campos a un lado y el PDF al otro, para copiar sus datos mirándolo. */}
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div className="grid content-start gap-6">
                                    <EquipoServicioCard servicio={servicio} />

                                    <div className="grid gap-2">
                                        <Label htmlFor="empresa_tercero_id">
                                            Laboratorio tercero
                                        </Label>
                                        <Combobox
                                            id="empresa_tercero_id"
                                            value={empresaTerceroId}
                                            onValueChange={setEmpresaTerceroId}
                                            options={empresasTerceras.map(
                                                (empresa) => ({
                                                    id: empresa.id,
                                                    label: empresa.nombre,
                                                }),
                                            )}
                                            placeholder="Selecciona un laboratorio"
                                            searchPlaceholder="Buscar laboratorio..."
                                        />
                                        <input
                                            type="hidden"
                                            name="empresa_tercero_id"
                                            value={empresaTerceroId}
                                        />
                                        <InputError
                                            message={errors.empresa_tercero_id}
                                        />
                                    </div>

                                    {/* Para saber cuándo y con qué documento hizo el tercero el servicio. */}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="numero_certificado">
                                                Número de certificado
                                            </Label>
                                            <Input
                                                id="numero_certificado"
                                                name="numero_certificado"
                                                defaultValue={
                                                    servicio.numero_certificado ??
                                                    ''
                                                }
                                                maxLength={100}
                                                placeholder="Ej. CERT-2026-0154"
                                                data-test="servicio-tercero-numero-certificado"
                                            />
                                            <InputError
                                                message={
                                                    errors.numero_certificado
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="fecha_servicio">
                                                Fecha del servicio
                                            </Label>
                                            <Input
                                                id="fecha_servicio"
                                                name="fecha_servicio"
                                                type="date"
                                                defaultValue={
                                                    servicio.fecha_servicio ??
                                                    ''
                                                }
                                                data-test="servicio-tercero-fecha-servicio"
                                            />
                                            <InputError
                                                message={errors.fecha_servicio}
                                            />
                                        </div>
                                    </div>

                                    {servicio.tipo_servicio ===
                                    'MANTENIMIENTO' ? (
                                        <CodigosServicioFields
                                            codigoCompra={
                                                servicio.codigo_compra ?? ''
                                            }
                                            codigoDespacho={
                                                servicio.codigo_despacho ?? ''
                                            }
                                            errors={errors}
                                        />
                                    ) : null}

                                    <div className="grid gap-2">
                                        <Label htmlFor="pdf_servicio">
                                            Documentación del servicio (PDF)
                                        </Label>
                                        <Input
                                            id="pdf_servicio"
                                            name="pdf_servicio"
                                            type="file"
                                            accept="application/pdf"
                                            onChange={(evento) =>
                                                setArchivoPdf(
                                                    evento.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            data-test="servicio-tercero-pdf-input"
                                        />
                                        <InputError
                                            message={errors.pdf_servicio}
                                        />
                                    </div>
                                </div>

                                <VistaPreviaPdf
                                    archivo={archivoPdf}
                                    urlActual={servicio.pdf_url}
                                    className="h-96 lg:h-auto lg:min-h-[60vh]"
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    data-test="servicio-tercero-update-submit"
                                >
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
