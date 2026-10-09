import type { ServicioTerceroListado } from '@/types';

type Props = {
    servicio: ServicioTerceroListado;
};

/** Tarjeta con el equipo (y su orden de trabajo y cliente) al que pertenece un servicio de tercero. */
export default function EquipoServicioCard({ servicio }: Props) {
    return (
        <div
            className="rounded-lg border bg-muted/40 p-4"
            data-test="servicio-tercero-equipo-tarjeta"
        >
            <p className="text-xs text-muted-foreground">Equipo</p>
            <p className="font-medium">
                {servicio.equipo_codigo} · {servicio.equipo_modelo}
            </p>
            <p className="text-sm text-muted-foreground">
                {servicio.orden_trabajo_codigo}
                {servicio.cliente_nombre ? ` · ${servicio.cliente_nombre}` : ''}
            </p>
        </div>
    );
}
