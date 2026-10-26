import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Modules/Equipos/Http/Controllers/EquipoHistoricoController';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
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
import { MOTIVO_SIN_CONEXION, motivoErrorHttp } from '@/lib/motivo-error-http';
import type { Equipo, EquipoHistorico, IngresoOption } from '@/types';

/** Extensiones del adjunto (ver EquipoHistorico::EXTENSIONES_ADJUNTO). */
const EXTENSIONES_ADJUNTO = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx';

type Props = {
    equipo: Equipo;
    empresasTerceras: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAgregado: (historico: EquipoHistorico) => void;
};

type DatosHistorico = {
    tipo_servicio: string;
    fecha_servicio: string;
    es_tercero: boolean;
    empresa_tercero_id: string;
    adjunto: File | null;
};

/** Hoy en formato AAAA-MM-DD (hora local), el máximo de la fecha del servicio. */
function hoy(): string {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());

    return fecha.toISOString().slice(0, 10);
}

/**
 * Agrega a mano un servicio histórico al equipo (el mismo dato que se importa desde un
 * CSV), con su adjunto opcional.
 */
export default function AgregarHistoricoModal({
    equipo,
    empresasTerceras,
    open,
    onOpenChange,
    onAgregado,
}: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useHttp<DatosHistorico, EquipoHistorico>({
            tipo_servicio: '',
            fecha_servicio: '',
            es_tercero: false,
            empresa_tercero_id: '',
            adjunto: null,
        });

    const cambiarApertura = (abierta: boolean) => {
        if (!abierta) {
            reset();
            clearErrors();
        }

        onOpenChange(abierta);
    };

    const guardar = () => {
        post(store.url(equipo.id), {
            onSuccess: (historico) => {
                toast.success('Histórico agregado.');
                onAgregado(historico);
                cambiarApertura(false);
            },
            onHttpException: (respuesta) => {
                toast.error(
                    `No se pudo agregar el histórico: ${motivoErrorHttp(respuesta)}`,
                );
            },
            onNetworkError: () => {
                toast.error(
                    `No se pudo agregar el histórico: ${MOTIVO_SIN_CONEXION}`,
                );
            },
        }).catch(() => undefined);
    };

    return (
        <Dialog open={open} onOpenChange={cambiarApertura}>
            <DialogContent data-test="agregar-historico-modal">
                <DialogHeader>
                    <DialogTitle>Agregar historial</DialogTitle>
                    <DialogDescription>
                        Registra un servicio anterior del equipo {equipo.codigo}{' '}
                        (por ejemplo, de otro sistema), con su certificado o
                        informe.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="historico-tipo">
                            Tipo de servicio{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={data.tipo_servicio}
                            onValueChange={(valor) =>
                                setData('tipo_servicio', valor)
                            }
                        >
                            <SelectTrigger
                                id="historico-tipo"
                                className="w-full"
                                data-test="historico-tipo"
                            >
                                <SelectValue placeholder="Selecciona un tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="MANTENIMIENTO">
                                    Mantenimiento
                                </SelectItem>
                                <SelectItem value="CALIBRACION">
                                    Calibración
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.tipo_servicio} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="historico-fecha">
                            Fecha del servicio{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="historico-fecha"
                            type="date"
                            max={hoy()}
                            value={data.fecha_servicio}
                            onChange={(event) =>
                                setData('fecha_servicio', event.target.value)
                            }
                            data-test="historico-fecha"
                        />
                        <InputError message={errors.fecha_servicio} />
                    </div>

                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="historico-tercero"
                            checked={data.es_tercero}
                            onCheckedChange={(marcado) => {
                                setData('es_tercero', marcado === true);

                                if (marcado !== true) {
                                    setData('empresa_tercero_id', '');
                                }
                            }}
                            data-test="historico-tercero"
                        />
                        <Label
                            htmlFor="historico-tercero"
                            className="font-normal"
                        >
                            Lo hizo un tercero
                        </Label>
                    </div>

                    {data.es_tercero ? (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="historico-empresa-tercero">
                                Empresa tercera{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Combobox
                                id="historico-empresa-tercero"
                                value={data.empresa_tercero_id}
                                onValueChange={(valor) =>
                                    setData('empresa_tercero_id', valor)
                                }
                                options={empresasTerceras.map((empresa) => ({
                                    id: empresa.id,
                                    label: empresa.nombre,
                                }))}
                                placeholder="Selecciona una empresa tercera"
                                searchPlaceholder="Buscar empresa tercera..."
                                emptyMessage="No hay empresas terceras registradas."
                            />
                            <InputError message={errors.empresa_tercero_id} />
                        </div>
                    ) : null}

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="historico-adjunto">Adjunto</Label>
                        <Input
                            id="historico-adjunto"
                            type="file"
                            accept={EXTENSIONES_ADJUNTO}
                            onChange={(event) =>
                                setData(
                                    'adjunto',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                            data-test="historico-adjunto"
                        />
                        <p className="text-xs text-muted-foreground">
                            Opcional. PDF, imagen, Word o Excel de hasta 20 MB.
                        </p>
                        <InputError message={errors.adjunto} />
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => cambiarApertura(false)}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        onClick={guardar}
                        loading={processing}
                        data-test="historico-guardar"
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
