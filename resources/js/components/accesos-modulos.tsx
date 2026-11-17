import { usePage } from '@inertiajs/react';
import { ClipboardCheck, Gauge, PackageCheck, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import BotonAtajo from '@/components/boton-atajo';
import { usePermissions } from '@/hooks/use-permissions';
import { index as calibracionesIndex } from '@/routes/calibraciones';
import { index as despachosIndex } from '@/routes/despachos';
import { index as ingresosIndex } from '@/routes/ingresos';
import { index as mantenimientosIndex } from '@/routes/mantenimientos';

type Modulo = {
    permiso: string;
    titulo: string;
    etiqueta: string;
    icono: LucideIcon;
    url: string;
    pendientes: number;
};

/** Los módulos de la operación que el usuario puede ver, con sus pendientes. */
export function useModulosOperacion(): Modulo[] {
    const { pendientes } = usePage().props;
    const { can } = usePermissions();

    const modulos: Modulo[] = [
        {
            permiso: 'ingresos.ver',
            titulo: 'Ingresos',
            etiqueta: 'Ingresos',
            icono: PackageCheck,
            url: ingresosIndex().url,
            pendientes: pendientes?.ingresos ?? 0,
        },
        {
            permiso: 'mantenimientos.ver',
            titulo: 'Mantenimientos',
            etiqueta: 'Mantenim.',
            icono: ClipboardCheck,
            url: mantenimientosIndex().url,
            pendientes:
                (pendientes?.mantenimientos?.local ?? 0) +
                (pendientes?.mantenimientos?.tercero ?? 0),
        },
        {
            permiso: 'calibraciones.ver',
            titulo: 'Calibraciones',
            etiqueta: 'Calibración',
            icono: Gauge,
            url: calibracionesIndex().url,
            pendientes:
                (pendientes?.calibraciones?.local ?? 0) +
                (pendientes?.calibraciones?.tercero ?? 0),
        },
        {
            permiso: 'despachos.ver',
            titulo: 'Despachos',
            etiqueta: 'Despachos',
            icono: Truck,
            url: despachosIndex().url,
            pendientes: pendientes?.despachos ?? 0,
        },
    ];

    return modulos.filter((modulo) => can(modulo.permiso));
}

/**
 * Accesos a los módulos de la operación (Ingresos, Mantenimientos, Calibraciones y
 * Despachos) en la barra inferior del móvil, solo los que el rol del usuario puede ver. El
 * de la página actual aparece levantado. En escritorio ya están en el menú lateral.
 */
export default function AccesosModulos({ modulos }: { modulos: Modulo[] }) {
    const { url } = usePage();
    const ruta = url.split('?')[0];

    return modulos.map((modulo) => (
        <BotonAtajo
            key={modulo.url}
            href={modulo.url}
            icono={modulo.icono}
            titulo={modulo.titulo}
            etiqueta={modulo.etiqueta}
            cantidad={modulo.pendientes}
            activo={ruta === modulo.url || ruta.startsWith(`${modulo.url}/`)}
            soloMovil
            dataTest={`modulo-${modulo.permiso.split('.')[0]}`}
        />
    ));
}
