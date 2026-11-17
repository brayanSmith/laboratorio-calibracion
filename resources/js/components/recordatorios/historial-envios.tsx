import { Link, router } from '@inertiajs/react';
import { Eye, MailCheck, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { etiquetaPaginacion } from '@/lib/etiqueta-paginacion';
import { cn } from '@/lib/utils';
import type { EnvioRecordatorio, EnviosRecordatorioPaginator } from '@/types';

const TIPOS: Record<EnvioRecordatorio['tipo'], string> = {
    cliente: 'Cliente',
    bahia: 'Resumen por bahía',
    copia: 'Con copia',
};

const ESTADOS: Record<
    EnvioRecordatorio['estado'],
    { etiqueta: string; clase: string }
> = {
    EN_COLA: {
        etiqueta: 'En cola',
        clase: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    },
    ENVIADO: {
        etiqueta: 'Enviado',
        clase: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    },
    FALLIDO: {
        etiqueta: 'Falló',
        clase: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    },
};

const ESTADOS_EQUIPO: Record<
    EnvioRecordatorio['equipos'][number]['estado'],
    { etiqueta: string; clase: string }
> = {
    VENCIDO: { etiqueta: 'Vencido', clase: 'text-red-700 dark:text-red-400' },
    PROGRAMADO: {
        etiqueta: 'Programado',
        clase: 'text-sky-700 dark:text-sky-400',
    },
    // Envíos registrados antes de unificar en "Programados".
    PROXIMO_A_VENCER: {
        etiqueta: 'Próximo a vencer',
        clase: 'text-amber-700 dark:text-amber-400',
    },
    AGENDADO: { etiqueta: 'Agendado', clase: 'text-sky-700 dark:text-sky-400' },
};

function fechaHora(valor: string): string {
    return new Date(valor).toLocaleString('es', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** "2026-11-20" como "20/11/2026", sin desfase de zona horaria. */
function fecha(valor: string): string {
    const [anio, mes, dia] = valor.split('-');

    return `${dia}/${mes}/${anio}`;
}

function EstadoEnvio({ envio }: { envio: EnvioRecordatorio }) {
    const estado = ESTADOS[envio.estado];

    return (
        <Badge
            variant="outline"
            className={estado.clase}
            title={envio.error ?? undefined}
        >
            {estado.etiqueta}
        </Badge>
    );
}

/** Lo que se envió en un correo: destinatario, asunto, estado y los equipos que llevaba. */
function DetalleEnvio({
    envio,
    onClose,
}: {
    envio: EnvioRecordatorio | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={envio !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {envio ? (
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{envio.asunto}</DialogTitle>
                        <DialogDescription>
                            Para {envio.destinatario} ({envio.correo}) ·{' '}
                            {TIPOS[envio.tipo]}
                            {envio.es_prueba ? ' · prueba' : ''}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <EstadoEnvio envio={envio} />
                        <span className="text-muted-foreground">
                            {envio.enviado_at
                                ? `Enviado el ${fechaHora(envio.enviado_at)}`
                                : `Registrado el ${fechaHora(envio.creado_at)}`}
                        </span>
                    </div>

                    {envio.error ? (
                        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                            {envio.error}
                        </p>
                    ) : null}

                    <div className="max-h-96 overflow-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2">Equipo</th>
                                    <th className="px-3 py-2">
                                        Cliente / Bahía
                                    </th>
                                    <th className="px-3 py-2">
                                        Próximo servicio
                                    </th>
                                    <th className="px-3 py-2">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {envio.equipos.map((equipo, indice) => (
                                    <tr key={indice} className="border-t">
                                        <td className="px-3 py-2">
                                            <span className="font-medium">
                                                {equipo.codigo}
                                            </span>{' '}
                                            · {equipo.tipo_equipo}
                                        </td>
                                        <td className="px-3 py-2">
                                            {equipo.cliente}
                                            <span className="block text-xs text-muted-foreground">
                                                Bahía: {equipo.bahia}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2">
                                            {fecha(
                                                equipo.fecha_proximo_servicio,
                                            )}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-3 py-2',
                                                ESTADOS_EQUIPO[equipo.estado]
                                                    .clase,
                                            )}
                                        >
                                            {
                                                ESTADOS_EQUIPO[equipo.estado]
                                                    .etiqueta
                                            }
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            ) : null}
        </Dialog>
    );
}

/**
 * Historial de los correos del recordatorio: a quién se le envió, con qué asunto y
 * equipos, y si salió, para no tener que revisarlo en el buzón.
 */
export default function HistorialEnvios({
    envios,
}: {
    envios: EnviosRecordatorioPaginator;
}) {
    const [detalle, setDetalle] = useState<EnvioRecordatorio | null>(null);
    const [actualizando, setActualizando] = useState(false);

    /** Vuelve a pedir solo el historial (en la misma página), sin recargar la página. */
    function actualizar() {
        router.reload({
            only: ['envios'],
            onStart: () => setActualizando(true),
            onFinish: () => setActualizando(false),
        });
    }

    const botonActualizar = (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={actualizar}
            disabled={actualizando}
            data-test="recordatorio-envios-actualizar"
        >
            <RefreshCw className={cn(actualizando && 'animate-spin')} />
            {actualizando ? 'Actualizando…' : 'Actualizar'}
        </Button>
    );

    if (envios.total === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center">
                <MailCheck className="size-8 text-muted-foreground" />
                <p className="font-medium">
                    Aún no se ha enviado ningún correo
                </p>
                <p className="max-w-md text-sm text-muted-foreground">
                    Aquí aparecerá cada correo del recordatorio (y las pruebas):
                    a quién se le envió, con qué equipos y si llegó a salir.
                </p>
                <div className="mt-2">{botonActualizar}</div>
            </div>
        );
    }

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {envios.total} {envios.total === 1 ? 'correo' : 'correos'}{' '}
                    en el historial. "En cola" pasa a "Enviado" cuando el correo
                    sale.
                </p>
                {botonActualizar}
            </div>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">Fecha</th>
                            <th className="px-4 py-2.5 font-medium">
                                Destinatario
                            </th>
                            <th className="px-4 py-2.5 font-medium">Tipo</th>
                            <th className="px-4 py-2.5 font-medium">Asunto</th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Equipos
                            </th>
                            <th className="px-4 py-2.5 font-medium">Estado</th>
                            <th className="px-4 py-2.5">
                                <span className="sr-only">Ver</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {envios.data.map((envio) => (
                            <tr
                                key={envio.id}
                                className="border-t align-top"
                                data-test="recordatorio-envio"
                            >
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {fechaHora(envio.creado_at)}
                                </td>
                                <td className="px-4 py-3">
                                    <span className="font-medium">
                                        {envio.destinatario}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {envio.correo}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        <Badge variant="secondary">
                                            {TIPOS[envio.tipo]}
                                        </Badge>
                                        {envio.es_prueba ? (
                                            <Badge variant="outline">
                                                Prueba
                                            </Badge>
                                        ) : null}
                                    </div>
                                </td>
                                <td className="max-w-72 px-4 py-3">
                                    <span className="line-clamp-2">
                                        {envio.asunto}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {envio.equipos.length}
                                </td>
                                <td className="px-4 py-3">
                                    <EstadoEnvio envio={envio} />
                                    {envio.error ? (
                                        <span className="mt-1 line-clamp-2 block max-w-56 text-xs text-red-700 dark:text-red-400">
                                            {envio.error}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDetalle(envio)}
                                        data-test="recordatorio-envio-ver"
                                    >
                                        <Eye /> Ver
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {envios.last_page > 1 ? (
                <div className="flex flex-wrap items-center gap-1">
                    {envios.links.map((link, indice) =>
                        link.url ? (
                            <Button
                                key={indice}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                asChild
                            >
                                <Link
                                    href={link.url}
                                    only={['envios']}
                                    preserveState
                                    preserveScroll
                                >
                                    {etiquetaPaginacion(link.label)}
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                key={indice}
                                variant="outline"
                                size="sm"
                                disabled
                            >
                                {etiquetaPaginacion(link.label)}
                            </Button>
                        ),
                    )}
                </div>
            ) : null}

            <DetalleEnvio envio={detalle} onClose={() => setDetalle(null)} />
        </div>
    );
}
