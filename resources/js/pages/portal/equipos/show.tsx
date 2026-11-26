import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, History, Power } from 'lucide-react';
import { useState } from 'react';
import { CeldaEquipo } from '@/components/equipos/celdas-equipo';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatearFecha } from '@/lib/fechas';
import { HistorialEquipo, etapaActual } from '@/pages/seguimiento/show';
import type { EquipoSeguimiento } from '@/pages/seguimiento/show';
import { index } from '@/routes/portal/equipos';
import type { HistoricoPortal } from '@/types';

type EquipoDetalle = {
    id: number;
    codigo: string;
    tipo_equipo: string | null;
    clase_equipo: string | null;
    modelo: string | null;
    numero_serie: string | null;
    fabricante: string | null;
    area: string | null;
    bahia: string | null;
    activo: boolean;
    motivo_inactivo: string | null;
};

type PasoPorIngreso = EquipoSeguimiento & {
    id: number;
    ingreso: string;
    bahia: string | null;
    estado_ingreso: string;
    fecha_ingreso_ingreso: string | null;
};

type Props = {
    equipo: EquipoDetalle;
    traza: PasoPorIngreso[];
    historicos: HistoricoPortal[];
};

/**
 * Un equipo del cliente: sus datos, cada vez que pasó por el laboratorio (con la etapa en
 * que va y los documentos de los servicios terminados) y los servicios históricos.
 */
export default function PortalEquipo({ equipo, traza, historicos }: Props) {
    const [historial, setHistorial] = useState<PasoPorIngreso | null>(null);

    return (
        <>
            <Head title={`Equipo ${equipo.codigo}`} />

            <div className="space-y-6">
                <Button variant="ghost" size="sm" asChild className="-ml-2">
                    <Link href={index().url}>
                        <ArrowLeft className="size-4" />
                        Mis equipos
                    </Link>
                </Button>

                <div className="rounded-lg border bg-background p-4">
                    <CeldaEquipo
                        codigo={equipo.codigo}
                        tipo={equipo.tipo_equipo}
                        clase={equipo.clase_equipo}
                        modelo={equipo.modelo}
                        serie={equipo.numero_serie}
                    />
                    <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                        <Dato etiqueta="Fabricante" valor={equipo.fabricante} />
                        <Dato etiqueta="Área" valor={equipo.area} />
                        <Dato etiqueta="Bahía" valor={equipo.bahia} />
                    </dl>

                    {!equipo.activo ? (
                        <div
                            className="mt-4 flex items-start gap-2 rounded-md bg-slate-100 p-3 text-sm text-slate-700 dark:bg-slate-500/15 dark:text-slate-300"
                            data-test="portal-equipo-inactivo"
                        >
                            <Power className="mt-0.5 size-4 shrink-0" />
                            <span>
                                <strong>Equipo inactivo.</strong> Ya no se le
                                programan servicios; su historial se conserva.
                                {equipo.motivo_inactivo
                                    ? ` Motivo: ${equipo.motivo_inactivo}`
                                    : ''}
                            </span>
                        </div>
                    ) : null}
                </div>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Historial de servicios"
                        description="Cada vez que el equipo pasó por el laboratorio, del más reciente al más antiguo."
                    />

                    {traza.length === 0 ? (
                        <p className="rounded-lg border bg-background p-6 text-center text-sm text-muted-foreground">
                            Este equipo todavía no ha pasado por el laboratorio.
                        </p>
                    ) : (
                        <ul className="space-y-3" data-test="portal-traza">
                            {traza.map((paso) => (
                                <li
                                    key={paso.id}
                                    className="space-y-3 rounded-lg border bg-background p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium">
                                                Ingreso {paso.ingreso}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {paso.tipo_servicio}
                                                {paso.bahia
                                                    ? ` · ${paso.bahia}`
                                                    : ''}
                                                {paso.fecha_ingreso_ingreso
                                                    ? ` · ${formatearFecha(paso.fecha_ingreso_ingreso)}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {etapaActual(paso)}
                                            </Badge>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setHistorial(paso)
                                                }
                                                data-test="portal-ver-traza"
                                            >
                                                <History className="size-4" />
                                                Ver traza
                                            </Button>
                                        </div>
                                    </div>

                                    {paso.documentos.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {paso.documentos.map(
                                                (documento) => (
                                                    <Button
                                                        key={documento.url}
                                                        variant="secondary"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={documento.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            data-test="portal-documento"
                                                        >
                                                            <FileText className="size-4" />
                                                            {documento.nombre}
                                                            {documento.fecha
                                                                ? ` · ${formatearFecha(documento.fecha)}`
                                                                : ''}
                                                            {documento.laboratorio
                                                                ? ` · ${documento.laboratorio}`
                                                                : ''}
                                                        </a>
                                                    </Button>
                                                ),
                                            )}
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {historicos.length > 0 ? (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Servicios anteriores"
                            description="Servicios registrados antes de llevar el control en este sistema."
                        />
                        <ul
                            className="divide-y rounded-lg border bg-background"
                            data-test="portal-historicos"
                        >
                            {historicos.map((historico) => (
                                <li
                                    key={historico.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {historico.tipo_servicio ===
                                            'CALIBRACION'
                                                ? 'Calibración'
                                                : 'Mantenimiento'}
                                        </span>{' '}
                                        ·{' '}
                                        {formatearFecha(
                                            historico.fecha_servicio,
                                        )}
                                        {historico.empresa_tercero
                                            ? ` · ${historico.empresa_tercero}`
                                            : ''}
                                    </span>
                                    {historico.adjunto_url ? (
                                        <a
                                            href={historico.adjunto_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-primary hover:underline"
                                        >
                                            <FileText className="size-4" />
                                            {historico.adjunto_nombre ??
                                                'Ver documento'}
                                        </a>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
            </div>

            <HistorialEquipo
                equipo={historial}
                onClose={() => setHistorial(null)}
            />
        </>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd>{valor || '—'}</dd>
        </div>
    );
}
