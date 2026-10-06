import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MantenimientoController from '@/actions/App/Http/Controllers/MantenimientoController';
import FinalizarMantenimientoModal from '@/components/mantenimientos/finalizar-mantenimiento-modal';
import MantenimientoChecklist from '@/components/mantenimientos/mantenimiento-checklist';
import MantenimientoComentarios from '@/components/mantenimientos/mantenimiento-comentarios';
import MantenimientoDefectos from '@/components/mantenimientos/mantenimiento-defectos';
import MantenimientoGaleria from '@/components/mantenimientos/mantenimiento-galeria';
import MantenimientoItemsUsados from '@/components/mantenimientos/mantenimiento-items-usados';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useElapsedTime } from '@/hooks/use-elapsed-time';
import type { Mantenimiento, MantenimientoItemOption } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
    items: MantenimientoItemOption[];
    /** Momento en que se inició, para el cronómetro en vivo. */
    iniciadoEn: Date | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Gestiona un mantenimiento ya iniciado: muestra los datos de referencia (equipo,
 * fecha, técnico, descripción) y un cronómetro en vivo desde que se inició, permite
 * registrar el estado inicial del equipo, y reúne el checklist, los defectos
 * identificados, los ítems usados, los comentarios y la galería de fotos.
 */
export default function GestionarMantenimientoModal({
    mantenimiento,
    items,
    iniciadoEn,
    open,
    onOpenChange,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <GestionarMantenimientoFormulario
                    key={`${mantenimiento.id}-${String(open)}`}
                    mantenimiento={mantenimiento}
                    items={items}
                    iniciadoEn={iniciadoEn}
                    onSuccess={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

type FormularioProps = {
    mantenimiento: Mantenimiento;
    items: MantenimientoItemOption[];
    iniciadoEn: Date | null;
    onSuccess: () => void;
};

function GestionarMantenimientoFormulario({
    mantenimiento,
    items,
    iniciadoEn,
    onSuccess,
}: FormularioProps) {
    const [estadoInicialEquipo, setEstadoInicialEquipo] = useState<
        'OPERATIVO' | 'FUERA_DE_SERVICIO'
    >(
        mantenimiento.estado_inicial_equipo === 'FUERA_DE_SERVICIO'
            ? 'FUERA_DE_SERVICIO'
            : 'OPERATIVO',
    );
    const tiempoTranscurrido = useElapsedTime(iniciadoEn ?? new Date());
    const [finalizarOpen, setFinalizarOpen] = useState(false);

    return (
        <>
            <Form
                {...MantenimientoController.update.form(mantenimiento.id)}
                className="min-w-0 space-y-6"
                onSuccess={onSuccess}
            >
                {({ errors, processing }) => (
                    <>
                        <DialogHeader>
                            <DialogTitle>Gestionar mantenimiento</DialogTitle>
                            <DialogDescription>
                                {iniciadoEn ? (
                                    <>
                                        En curso desde hace{' '}
                                        <span
                                            className="font-mono"
                                            data-test="tiempo-transcurrido"
                                        >
                                            {tiempoTranscurrido}
                                        </span>
                                    </>
                                ) : (
                                    'Revisa los datos del mantenimiento'
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2 rounded-md border p-3">
                            <p className="text-sm font-medium">
                                {mantenimiento.equipo.codigo} ·{' '}
                                {mantenimiento.equipo.tipo_equipo.nombre} ·{' '}
                                {mantenimiento.equipo.modelo}
                            </p>
                            {mantenimiento.equipo.cliente ? (
                                <p className="text-xs text-muted-foreground">
                                    {mantenimiento.equipo.cliente.nombre}
                                </p>
                            ) : null}

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <p>
                                    <span className="text-muted-foreground">
                                        Fabricante:
                                    </span>{' '}
                                    {mantenimiento.equipo.fabricante.nombre}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        N° de serie:
                                    </span>{' '}
                                    {mantenimiento.equipo.numero_serie}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Tecnología:
                                    </span>{' '}
                                    {mantenimiento.equipo.tipo_tecnologia}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Tipo de mantenimiento:
                                    </span>{' '}
                                    {
                                        mantenimiento.equipo.tipo_equipo
                                            .tipo_mantenimiento
                                    }
                                </p>
                            </div>

                            {mantenimiento.equipo.ficha_tecnica ? (
                                <div className="grid grid-cols-2 gap-2 border-t pt-2 text-xs">
                                    <p>
                                        <span className="text-muted-foreground">
                                            País:
                                        </span>{' '}
                                        {
                                            mantenimiento.equipo.ficha_tecnica
                                                .pais_procedencia
                                        }
                                    </p>
                                    <p>
                                        <span className="text-muted-foreground">
                                            N° activo:
                                        </span>{' '}
                                        {
                                            mantenimiento.equipo.ficha_tecnica
                                                .numero_activo
                                        }
                                    </p>
                                    <p>
                                        <span className="text-muted-foreground">
                                            Proveedor:
                                        </span>{' '}
                                        {
                                            mantenimiento.equipo.ficha_tecnica
                                                .proveedor
                                        }
                                    </p>
                                    <p>
                                        <span className="text-muted-foreground">
                                            Costo USD:
                                        </span>{' '}
                                        {
                                            mantenimiento.equipo.ficha_tecnica
                                                .costo_usd
                                        }
                                    </p>
                                </div>
                            ) : null}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label className="text-xs text-muted-foreground">
                                    Fecha
                                </Label>
                                <p className="text-sm">
                                    {mantenimiento.fecha_mantenimiento}
                                </p>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs text-muted-foreground">
                                    Técnico
                                </Label>
                                <p className="text-sm">
                                    {mantenimiento.tecnico_nombre}
                                </p>
                            </div>
                            <div className="grid gap-1 sm:col-span-2">
                                <Label className="text-xs text-muted-foreground">
                                    Descripción
                                </Label>
                                <p className="text-sm">
                                    {mantenimiento.descripcion ||
                                        'Sin descripción'}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs text-muted-foreground">
                                Estado inicial del equipo
                            </Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={estadoInicialEquipo}
                                onValueChange={(value) => {
                                    if (value) {
                                        setEstadoInicialEquipo(
                                            value as
                                                | 'OPERATIVO'
                                                | 'FUERA_DE_SERVICIO',
                                        );
                                    }
                                }}
                                className="w-full"
                                data-test="estado-inicial-equipo-toggle"
                            >
                                <ToggleGroupItem
                                    value="OPERATIVO"
                                    className="grow basis-0 data-[state=on]:!border-emerald-500 data-[state=on]:!bg-emerald-500 data-[state=on]:!text-white"
                                >
                                    Operativo
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="FUERA_DE_SERVICIO"
                                    className="grow basis-0 data-[state=on]:!border-red-500 data-[state=on]:!bg-red-500 data-[state=on]:!text-white"
                                >
                                    Fuera de servicio
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        <input
                            type="hidden"
                            name="estado_inicial_equipo"
                            value={estadoInicialEquipo}
                        />
                        <input
                            type="hidden"
                            name="fecha_mantenimiento"
                            value={mantenimiento.fecha_mantenimiento}
                        />
                        <input
                            type="hidden"
                            name="estado_mantenimiento"
                            value={mantenimiento.estado_mantenimiento}
                        />
                        <input
                            type="hidden"
                            name="tecnico_id"
                            value={mantenimiento.tecnico_id}
                        />

                        <MantenimientoChecklist
                            checklist={mantenimiento.checklist}
                        />

                        <MantenimientoDefectos
                            defectos={mantenimiento.defectos}
                            errors={errors}
                        />

                        <MantenimientoItemsUsados
                            itemsUsados={mantenimiento.items_usados}
                            items={items}
                            errors={errors}
                        />

                        <MantenimientoComentarios
                            comentarios={mantenimiento.comentarios}
                            errors={errors}
                        />

                        <MantenimientoGaleria
                            galeria={mantenimiento.galeria}
                            errors={errors}
                        />

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cerrar</Button>
                            </DialogClose>
                            <Button
                                type="button"
                                className="!bg-emerald-600 !text-white hover:!bg-emerald-700"
                                onClick={() => setFinalizarOpen(true)}
                                data-test="abrir-finalizar-button"
                            >
                                Finalizar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="gestionar-mantenimiento-submit"
                            >
                                Guardar
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>

            <FinalizarMantenimientoModal
                mantenimientoId={mantenimiento.id}
                iniciadoEn={iniciadoEn}
                open={finalizarOpen}
                onOpenChange={setFinalizarOpen}
                onFinalizado={() => {
                    setFinalizarOpen(false);
                    onSuccess();
                }}
            />
        </>
    );
}
