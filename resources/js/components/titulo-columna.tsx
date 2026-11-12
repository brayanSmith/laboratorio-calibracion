import type { LucideIcon } from 'lucide-react';
import { iconoDeColumna } from '@/lib/iconos-columnas';

/**
 * Título de una columna con su ícono (el del mapa de íconos de columnas, o el que se
 * pase), para las tablas que no usan DataTable.
 */
export default function TituloColumna({
    titulo,
    icono,
}: {
    titulo: string;
    icono?: LucideIcon;
}) {
    const Icono = icono ?? iconoDeColumna(titulo);

    return (
        <span className="inline-flex items-center gap-1">
            {Icono ? (
                <Icono
                    className="h-4 w-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
            ) : null}
            {titulo}
        </span>
    );
}
